<?php

namespace App\Console\Commands\Timekeeping;

use Illuminate\Console\Command;
use App\Models\RfidLedger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use App\Notifications\LedgerProcessingFailedNotification;
use Carbon\Carbon;

/**
 * CheckDeviceHealthCommand
 * 
 * Monitors RFID device health based on last scan timestamps.
 * Detects offline devices and sends alerts to HR Manager.
 * Scheduled to run every 2 minutes.
 * 
 * Phase 6, Task 6.1.2: Supporting scheduled command
 * 
 * @package App\Console\Commands\Timekeeping
 */
class CheckDeviceHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'timekeeping:check-device-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check RFID device health and alert on offline devices';

    /**
     * Device offline threshold in minutes.
     */
    private const OFFLINE_THRESHOLD_MINUTES = 10;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking RFID device health...');

        try {
            // Get last scan time per device
            $deviceLastScan = RfidLedger::select('device_id')
                ->selectRaw('MAX(scan_timestamp) as last_scan')
                ->groupBy('device_id')
                ->get();

            if ($deviceLastScan->isEmpty()) {
                $this->comment('No devices found in ledger');
                return Command::SUCCESS;
            }

            $offlineDevices = [];
            $offlineThreshold = Carbon::now()->subMinutes(self::OFFLINE_THRESHOLD_MINUTES);

            foreach ($deviceLastScan as $device) {
                $lastScan = Carbon::parse($device->last_scan);
                
                if ($lastScan->lt($offlineThreshold)) {
                    $offlineDevices[] = [
                        'device_id' => $device->device_id,
                        'last_scan' => $lastScan->toDateTimeString(),
                        'minutes_offline' => $lastScan->diffInMinutes(Carbon::now())
                    ];
                }
            }

            if (empty($offlineDevices)) {
                $this->info('✓ All devices are online');
                return Command::SUCCESS;
            }

            // Display offline devices
            $offlineCount = count($offlineDevices);
            $this->warn("⚠ {$offlineCount} device(s) offline:");
            $this->table(
                ['Device ID', 'Last Scan', 'Minutes Offline'],
                array_map(fn($d) => [$d['device_id'], $d['last_scan'], $d['minutes_offline']], $offlineDevices)
            );

            // Log offline devices
            Log::warning('[CheckDeviceHealth] Offline devices detected', [
                'offline_count' => count($offlineDevices),
                'devices' => $offlineDevices
            ]);

            // Notify HR Managers for devices offline > 30 minutes
            $criticalOffline = array_filter($offlineDevices, fn($d) => $d['minutes_offline'] > 30);
            
            if (!empty($criticalOffline)) {
                $this->notifyHRManagers($criticalOffline);
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Failed to check device health: {$e->getMessage()}");
            Log::error('[CheckDeviceHealth] Failed', ['error' => $e->getMessage()]);
            return Command::FAILURE;
        }
    }

    /**
     * Notify HR Managers about offline devices.
     *
     * @param array $offlineDevices
     * @return void
     */
    private function notifyHRManagers(array $offlineDevices): void
    {
        try {
            $hrManagers = \App\Models\User::role('HR Manager')->get();

            if ($hrManagers->isEmpty()) {
                $this->comment('No HR Managers found to notify');
                return;
            }

            $deviceList = implode(', ', array_column($offlineDevices, 'device_id'));
            $message = "Critical: " . count($offlineDevices) . " RFID device(s) offline for >30 minutes: {$deviceList}";

            Notification::send($hrManagers, new LedgerProcessingFailedNotification(
                $message,
                'warning',
                [
                    'type' => 'device_offline',
                    'devices' => $offlineDevices,
                    'timestamp' => now()->toDateTimeString()
                ]
            ));

            $this->info("✓ Notification sent to {$hrManagers->count()} HR Manager(s)");

        } catch (\Exception $e) {
            $this->error("Failed to send notifications: {$e->getMessage()}");
        }
    }
}
