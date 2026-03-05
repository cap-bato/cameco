<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class OffboardingSystemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder provides default template data for the offboarding system.
     * It creates reference data that can be used for configuring the system,
     * but NOT actual offboarding case data (which is created on-demand).
     */
    public function run(): void
    {
        Log::info('Starting OffboardingSystemSeeder...');

        // Note: Actual clearance items, assets, and access revocations are created
        // when an offboarding case is initiated through OffboardingService.
        // This seeder provides documentation and configuration data.

        // The default data is already embedded in the OffboardingService methods:
        // - createDefaultClearanceItems() - Creates 13 clearance items per case
        // - createDefaultAccessRevocations() - Creates 7 access revocation entries per case
        //
        // This seeder serves as a reference and can be extended to add:
        // - Configuration tables for customizable templates
        // - System settings for offboarding workflows
        // - Default questions for exit interviews

        Log::info('OffboardingSystemSeeder completed successfully');
    }
}
