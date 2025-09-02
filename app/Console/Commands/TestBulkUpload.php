<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessBulkProductUpload;
use Illuminate\Support\Facades\Storage;

class TestBulkUpload extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:bulk-upload {--file=test_bulk_upload.csv}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test the bulk upload functionality with a sample file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🧪 Testing Bulk Upload System');
        $this->info('============================');

        $fileName = $this->option('file');
        $filePath = base_path($fileName);

        if (!file_exists($filePath)) {
            $this->error("Test file not found: {$filePath}");
            return 1;
        }

        $this->info("Using test file: {$fileName}");
        $this->info("File size: " . filesize($filePath) . " bytes");

        // Copy test file to storage
        $storagePath = Storage::disk('local')->putFile('bulk-uploads', $filePath);
        $this->info("File stored at: {$storagePath}");

        // Create a test job
        $jobId = 'test_' . time() . '_' . uniqid();
        $this->info("Creating test job with ID: {$jobId}");

        try {
            $job = ProcessBulkProductUpload::dispatch($storagePath, null, $jobId);
            $this->info("✅ Job dispatched successfully");
            $this->info("Job ID: {$jobId}");
            $this->info("");
            $this->info("To process this job, run:");
            $this->info("  php artisan queue:work --once");
            $this->info("");
            $this->info("To monitor progress:");
            $this->info("  php artisan queue:status");
            $this->info("");
            $this->info("To check the bulk upload log:");
            $this->info("  tail -f storage/logs/bulk-upload.log");

        } catch (\Exception $e) {
            $this->error("❌ Failed to dispatch job: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
