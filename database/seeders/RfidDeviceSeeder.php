<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RfidDevice;
use Carbon\Carbon;

class RfidDeviceSeeder extends Seeder
{
    /**
     * Seed RFID devices for testing.
     * 
     * This seeder creates 5 sample RFID devices that can be used
     * for testing the timekeeping ledger system.
     */
    public function run(): void
    {
        $devices = [
            [
                'device_id' => 'GATE-01',
                'device_name' => 'Gate 1 Reader',
                'location' => 'Main Entrance - Gate 1',
                'status' => 'online',
                'last_heartbeat' => Carbon::now()->subMinutes(1),
                'config' => [
                    'ip_address' => '192.168.1.101',
                    'firmware_version' => '2.1.5',
                    'read_range_meters' => 1.5,
                ],
            ],
            [
                'device_id' => 'GATE-02',
                'device_name' => 'Gate 2 Reader',
                'location' => 'Side Entrance - Gate 2',
                'status' => 'online',
                'last_heartbeat' => Carbon::now()->subMinutes(2),
                'config' => [
                    'ip_address' => '192.168.1.102',
                    'firmware_version' => '2.1.5',
                    'read_range_meters' => 1.5,
                ],
            ],
            [
                'device_id' => 'CAFETERIA-01',
                'device_name' => 'Cafeteria Reader',
                'location' => 'Cafeteria Entrance',
                'status' => 'online',
                'last_heartbeat' => Carbon::now()->subMinutes(3),
                'config' => [
                    'ip_address' => '192.168.1.103',
                    'firmware_version' => '2.1.4',
                    'read_range_meters' => 1.2,
                ],
            ],
            [
                'device_id' => 'WAREHOUSE-01',
                'device_name' => 'Warehouse Reader',
                'location' => 'Warehouse Entry',
                'status' => 'online',
                'last_heartbeat' => Carbon::now()->subMinutes(5),
                'config' => [
                    'ip_address' => '192.168.1.104',
                    'firmware_version' => '2.1.3',
                    'read_range_meters' => 1.8,
                ],
            ],
            [
                'device_id' => 'OFFICE-01',
                'device_name' => 'Office Reader',
                'location' => 'Office Floor 2',
                'status' => 'maintenance',
                'last_heartbeat' => Carbon::now()->subHours(2),
                'config' => [
                    'ip_address' => '192.168.1.105',
                    'firmware_version' => '2.0.9',
                    'read_range_meters' => 1.0,
                ],
            ],
        ];

        foreach ($devices as $device) {
            RfidDevice::updateOrCreate(
                ['device_id' => $device['device_id']],
                $device
            );
        }

        $this->command->info('✅ RFID devices seeded successfully!');
        $this->command->info('Created/updated 5 RFID devices.');
    }
}
