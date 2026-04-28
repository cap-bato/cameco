<?php

namespace App\Console\Commands;

use App\Jobs\Timekeeping\ProcessRfidLedgerJob;
use Illuminate\Console\Command;

class ProcessRfidLedger extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:process-rfid-ledger
                            {--from-sequence-id= : Process entries from this sequence onward}
                            {--limit=1000 : Maximum entries to process}
                            {--force : Reprocess already-processed entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process RFID ledger entries into attendance events';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $fromSequenceId = $this->option('from-sequence-id');
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        if ($limit < 1) {
            $this->error('The --limit option must be at least 1.');
            return Command::FAILURE;
        }

        $this->info('Processing RFID ledger...');

        try {
            // Run synchronously so scheduler/manual run reflects real execution outcome
            // and updates cron status accurately.
            ProcessRfidLedgerJob::dispatchSync(
                $fromSequenceId !== null ? (int) $fromSequenceId : null,
                $limit,
                $force,
            );

            $this->info('RFID ledger processing completed successfully.');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('RFID ledger processing failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
