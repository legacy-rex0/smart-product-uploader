<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class StartQueueWorker extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:start {--daemon : Run in daemon mode} {--once : Process only one job}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start the queue worker for processing bulk uploads';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting queue worker for bulk upload processing...');
        
        if ($this->option('daemon')) {
            $this->info('Running in daemon mode (background)');
            $this->info('Use Ctrl+C to stop the worker');
            $this->call('queue:work', ['--daemon' => true]);
        } elseif ($this->option('once')) {
            $this->info('Processing one job and exiting...');
            $this->call('queue:work', ['--once' => true]);
        } else {
            $this->info('Running in foreground mode');
            $this->info('Use Ctrl+C to stop the worker');
            $this->call('queue:work');
        }
        
        return 0;
    }
}
