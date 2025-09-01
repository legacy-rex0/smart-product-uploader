<?php

namespace App\Jobs;

use App\Imports\ProductsImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class ProcessBulkProductUpload implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    private $filePath;
    private $userId;

    public function __construct(string $filePath, ?int $userId = null)
    {
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        try {
            Log::info('Starting bulk product upload job', [
                'file_path' => $this->filePath,
                'user_id' => $this->userId
            ]);

            $import = new ProductsImport();
            Excel::import($import, $this->filePath);

            $successCount = $import->getSuccessCount();
            $errors = $import->getErrors();

            Log::info('Bulk product upload job completed', [
                'success_count' => $successCount,
                'error_count' => count($errors),
                'errors' => $errors
            ]);

            if (count($errors) > 0) {
                Log::warning('Bulk upload completed with errors', [
                    'errors' => $errors
                ]);
            }

        } catch (\Exception $e) {
            Log::error('Bulk product upload job failed', [
                'file_path' => $this->filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Bulk product upload job failed permanently', [
            'file_path' => $this->filePath,
            'error' => $exception->getMessage()
        ]);
    }
}
