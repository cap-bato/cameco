<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PaymentMethodsSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Avoid duplicate seeding
        if (DB::table('payment_methods')->count() > 0) {
            $this->command->warn('payment_methods already seeded — skipping.');
            return;
        }

        $methods = [
            // Cash (Enabled by default — Decision #1: Cash is primary/default method)
            [
                'method_type' => 'cash',
                'display_name' => 'Cash Payment',
                'description' => 'Physical cash distribution via salary envelopes',
                'is_enabled' => true,
                'requires_employee_setup' => false,
                'supports_bulk_payment' => false,
                'transaction_fee' => 0,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'sort_order' => 1,
                'icon' => 'banknotes',
                'color_hex' => '#10b981',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Metrobank - InstaPay (Decision #1: Bank transfers disabled until Office Admin enables)
            [
                'method_type' => 'bank',
                'display_name' => 'Metrobank (InstaPay)',
                'description' => 'Real-time bank transfer via InstaPay network',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 10,
                'min_amount' => 1,
                'max_amount' => 50000,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'cutoff_time' => '17:00:00',
                'bank_code' => 'MBTC',
                'bank_name' => 'Metropolitan Bank & Trust Company',
                'file_format' => 'csv',
                'file_template' => json_encode([
                    'columns' => ['Account Number', 'Account Name', 'Amount', 'Reference'],
                    'delimiter' => ',',
                    'has_header' => true,
                ]),
                'sort_order' => 2,
                'icon' => 'building-library',
                'color_hex' => '#ef4444',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // BDO - PESONet (Decision #1: Disabled by default)
            [
                'method_type' => 'bank',
                'display_name' => 'BDO Unibank (PESONet)',
                'description' => 'Batch bank transfer via PESONet network (T+1 settlement)',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 25,
                'min_amount' => 1,
                'max_amount' => 1000000,
                'settlement_speed' => 'next_day',
                'processing_days' => 1,
                'cutoff_time' => '14:00:00',
                'bank_code' => 'BDO',
                'bank_name' => 'Banco de Oro',
                'file_format' => 'xlsx',
                'file_template' => json_encode([
                    'columns' => ['Account Number', 'Account Name', 'Amount', 'Particulars'],
                    'sheet_name' => 'Payroll',
                    'has_header' => true,
                ]),
                'sort_order' => 3,
                'icon' => 'building-library',
                'color_hex' => '#3b82f6',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // GCash via PayMongo
            // Decision #1: is_enabled=false, requires_employee_setup=true
            // Decision #4: PayMongo test mode only; GCash goes live in Phase 3
            [
                'method_type' => 'ewallet',
                'display_name' => 'GCash',
                'description' => 'E-wallet transfer via PayMongo API',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 15,
                'min_amount' => 1,
                'max_amount' => 100000,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'provider_name' => 'PayMongo',
                'api_endpoint' => 'https://api.paymongo.com/v1',
                'sort_order' => 4,
                'icon' => 'device-mobile',
                'color_hex' => '#0066ff',
                'created_at' => $now,
                'updated_at' => $now,
            ],

            // Maya (PayMaya) via PayMongo
            // Decision #1: is_enabled=false, requires_employee_setup=true
            // Decision #4: PayMongo test mode only; Maya goes live in Phase 3
            [
                'method_type' => 'ewallet',
                'display_name' => 'Maya (PayMaya)',
                'description' => 'E-wallet transfer via PayMongo API',
                'is_enabled' => false,
                'requires_employee_setup' => true,
                'supports_bulk_payment' => true,
                'transaction_fee' => 15,
                'min_amount' => 1,
                'max_amount' => 100000,
                'settlement_speed' => 'instant',
                'processing_days' => 0,
                'provider_name' => 'PayMongo',
                'api_endpoint' => 'https://api.paymongo.com/v1',
                'sort_order' => 5,
                'icon' => 'device-mobile',
                'color_hex' => '#00d632',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($methods as $method) {
            DB::table('payment_methods')->insert($method);
        }

        $this->command->info('✅ Payment methods seeded successfully!');
        $this->command->info('   - Cash: 1 (Enabled — primary method)');
        $this->command->info('   - Banks: 2 (Metrobank, BDO) — Disabled by default');
        $this->command->info('   - E-wallets: 2 (GCash, Maya via PayMongo) — Disabled by default');
    }
}
