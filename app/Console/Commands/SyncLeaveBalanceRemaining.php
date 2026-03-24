<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\LeaveBalance;

class SyncLeaveBalanceRemaining extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'leave-balances:sync-remaining';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync the physical remaining column for all leave balances to match the computed value (earned + carried_forward - used)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = 0;
        LeaveBalance::chunk(100, function ($balances) use (&$count) {
            foreach ($balances as $balance) {
                $computed = $balance->earned + $balance->carried_forward - $balance->used;
                $balance->updateQuietly(['remaining' => $computed]);
                $count++;
            }
        });
        $this->info("Updated {$count} leave balances.");
        return 0;
    }
}
