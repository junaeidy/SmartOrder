<?php

namespace App\Console\Commands;

use App\Models\QueueCounter;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ResetQueueCounter extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'queue:reset-counter';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset queue counter for a new day';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today();
        
        // Delete any existing counters for today (in case it exists)
        QueueCounter::where('date', $today->toDateString())->delete();
        
        // Create a fresh counter for today
        QueueCounter::create([
            'date' => $today,
            'last_number' => 0
        ]);
        
        // Delete old counters
        QueueCounter::where('date', '<', $today->toDateString())->delete();
        
        $this->info('Queue counter has been reset for ' . $today->toDateString());
    }
}