<?php

namespace App\Http\Controllers\HR\Timekeeping;

use App\Http\Controllers\Controller;
use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Models\ImportBatch;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ImportBatch::with('importedByUser:id,name')
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $batches = $query->paginate(20)->withQueryString();

        $summary = [
            'total_imports'    => ImportBatch::count(),
            'successful'       => ImportBatch::where('status', 'completed')->where('failed_records', 0)->count(),
            'failed'           => ImportBatch::where('status', 'failed')->count(),
            'pending'          => ImportBatch::whereIn('status', ['uploaded', 'processing'])->count(),
            'records_imported' => (int) ImportBatch::sum('successful_records'),
        ];

        return Inertia::render('HR/Timekeeping/Import/Index', [
            'batches' => $batches,
            'summary' => $summary,
            'filters' => $request->only(['status', 'date_from', 'date_to']),
        ]);
    }

    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file'        => 'required|file|mimes:csv,xlsx,xls|max:10240',
            'import_type' => 'required|in:attendance,schedule,correction',
        ]);

        $file       = $request->file('file');
        $storedPath = $file->store('imports/timekeeping', 'local');

        $batch = ImportBatch::create([
            'imported_by'    => auth()->id(),
            'file_name'      => $file->getClientOriginalName(),
            'file_path'      => $storedPath,
            'file_size'      => $file->getSize(),
            'import_type'    => $validated['import_type'],
            'total_records'  => 0,
            'status'         => 'uploaded',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'File uploaded. Use the process endpoint to begin import.',
            'data'    => [
                'batch_id'    => $batch->id,
                'file_name'   => $batch->file_name,
                'import_type' => $batch->import_type,
                'status'      => $batch->status,
                'uploaded_at' => $batch->created_at->toISOString(),
            ],
        ]);
    }

    public function process(int $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);

        if ($batch->status === 'completed') {
            return response()->json(['success' => false, 'message' => 'Batch already processed'], 422);
        }

        $batch->update(['status' => 'processing', 'started_at' => now()]);

        try {
            $result = match ($batch->import_type) {
                'attendance' => $this->processAttendanceFile($batch),
                default      => throw new \InvalidArgumentException("Import type '{$batch->import_type}' not yet supported"),
            };

            $batch->update([
                'status'             => 'completed',
                'total_records'      => $result['total'],
                'processed_records'  => $result['total'],
                'successful_records' => $result['success'],
                'failed_records'     => $result['failed'],
                'error_log'          => $result['failed'] > 0 ? json_encode($result['errors']) : null,
                'completed_at'       => now(),
            ]);

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            $batch->update([
                'status'    => 'failed',
                'error_log' => json_encode([['row' => 0, 'error' => $e->getMessage()]]),
                'completed_at' => now(),
            ]);

            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function processAttendanceFile(ImportBatch $batch): array
    {
        $path = storage_path("app/{$batch->file_path}");

        if (!file_exists($path)) {
            throw new \RuntimeException("Import file not found: {$batch->file_name}");
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open import file: {$batch->file_name}");
        }

        // Read header row
        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or unreadable');
        }
        $headers = array_map('trim', $headers);

        $total = $success = $failed = $skipped = 0;
        $errors = [];
        $rowNum = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $rowNum++;
            if (count($row) !== count($headers)) {
                $failed++;
                $errors[] = ['row' => $rowNum, 'error' => 'Column count mismatch'];
                continue;
            }

            $data = array_combine($headers, $row);
            $total++;

            try {
                // Expected columns: employee_number, event_date, event_type, event_time
                $employee = Employee::where('employee_number', trim($data['employee_number'] ?? ''))
                    ->first();
                if (!$employee) {
                    throw new \RuntimeException("Employee '{$data['employee_number']}' not found");
                }

                $eventDate = Carbon::parse($data['event_date'])->toDateString();
                $eventType = strtolower(trim($data['event_type']));

                if (!in_array($eventType, ['time_in', 'time_out', 'break_start', 'break_end'])) {
                    throw new \RuntimeException("Invalid event_type '{$eventType}'");
                }

                // Duplicate check
                $exists = AttendanceEvent::where('employee_id', $employee->id)
                    ->whereDate('event_date', $eventDate)
                    ->where('event_type', $eventType)
                    ->exists();

                if ($exists) {
                    $skipped++;
                    continue;
                }

                AttendanceEvent::create([
                    'employee_id'       => $employee->id,
                    'event_date'        => $eventDate,
                    'event_time'        => Carbon::parse($eventDate . ' ' . trim($data['event_time'])),
                    'event_type'        => $eventType,
                    'source'            => 'imported',
                    'imported_batch_id' => $batch->id,
                    'created_by'        => $batch->imported_by,
                ]);

                $success++;

            } catch (\Exception $e) {
                $failed++;
                $errors[] = ['row' => $rowNum, 'error' => $e->getMessage()];
            }
        }

        fclose($handle);

        return compact('total', 'success', 'failed', 'skipped', 'errors');
    }

    public function history(Request $request): JsonResponse
    {
        $batches = ImportBatch::with('importedByUser:id,name')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->status))
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json(['success' => true, 'data' => $batches]);
    }

    public function errors(int $id): JsonResponse
    {
        $batch = ImportBatch::findOrFail($id);

        $errors = $batch->error_log ? json_decode($batch->error_log, true) : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'batch_id'     => $id,
                'errors'       => $errors ?? [],
                'total_errors' => count($errors ?? []),
            ],
        ]);
    }
}

