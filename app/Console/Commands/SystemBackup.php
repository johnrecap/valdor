<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SystemBackup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:backup {--retention=20 : Number of backups to keep}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a system backup and cleanup old backups';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting system backup...');

        try {
            $retention = (int) $this->option('retention');
            $result = $backupService->createBackup($retention);

            if ($result['success']) {
                $this->info("Backup created successfully: " . $result['filename']);
                $this->info("Keep limit: " . $retention);
                Log::info('System backup created via scheduler', $result);
                return 0;
            } else {
                $this->error("Backup failed: " . ($result['message'] ?? 'Unknown error'));
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            return 1;
        }
    }
}
