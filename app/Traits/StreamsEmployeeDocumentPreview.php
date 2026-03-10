<?php

namespace App\Traits;

use App\Models\EmployeeDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait StreamsEmployeeDocumentPreview
{
    private function streamDocumentPreview(
        EmployeeDocument $document,
        string $downloadUrl,
        bool $wrapData = false,
        ?callable $onPreviewable = null
    ): StreamedResponse|JsonResponse {
        $disk = Storage::disk('employee_documents');

        if (!$disk->exists($document->file_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Document file not found on server.',
            ], 404);
        }

        $mime = $document->mime_type ?? 'application/octet-stream';
        $isPreviewable = $mime === 'application/pdf' || str_starts_with($mime, 'image/');

        if (!$isPreviewable) {
            $payload = [
                'success' => true,
                'previewable' => false,
                'message' => 'This file type cannot be previewed inline.',
            ];

            $downloadData = [
                'download_url' => $downloadUrl,
                'file_name' => $document->file_name,
                'mime_type' => $mime,
            ];

            if ($wrapData) {
                $payload['data'] = $downloadData;
            } else {
                $payload = array_merge($payload, $downloadData);
            }

            return response()->json($payload);
        }

        if ($onPreviewable) {
            $onPreviewable($mime);
        }

        return $disk->response(
            $document->file_path,
            $document->file_name,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . $document->file_name . '"',
                'Cache-Control' => 'private, no-store',
            ]
        );
    }
}
