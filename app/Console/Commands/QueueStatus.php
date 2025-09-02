<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class QueueStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of the queue system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Queue System Status');
        $this->info('==================');
        
        // Check jobs table
        $pendingJobs = DB::table('jobs')->count();
        $failedJobs = DB::table('failed_jobs')->count();
        
        $this->info("Pending jobs: {$pendingJobs}");
        $this->info("Failed jobs: {$failedJobs}");
        
        if ($pendingJobs > 0) {
            $this->warn("⚠️  There are {$pendingJobs} pending jobs. Start a queue worker to process them.");
            $this->info("Run: php artisan queue:start");
        }
        
        if ($failedJobs > 0) {
            $this->error("❌ There are {$failedJobs} failed jobs. Check them with: php artisan queue:failed");
        }
        
        if ($pendingJobs === 0 && $failedJobs === 0) {
            $this->info("✅ Queue is clean - no pending or failed jobs");
        }
        
        // Check bulk upload progress
        $this->info("\nBulk Upload Progress:");
        $this->info("=====================");
        
        $progressKeys = Cache::get('bulk_upload_progress_*');
        if ($progressKeys) {
            foreach ($progressKeys as $key => $progress) {
                $this->info("Job: " . substr($key, 24) . " - " . $progress['message']);
            }
        } else {
            $this->info("No active bulk uploads");
        }
        
        return 0;
    }
}
