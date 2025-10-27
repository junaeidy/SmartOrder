<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Discount;
use Carbon\Carbon;

class DeactivateExpiredDiscounts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discounts:deactivate-expired';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deactivate discounts that have passed their expiration date';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for expired discounts...');

        $now = Carbon::now();

        // Find active discounts where valid_until is in the past
        $expiredDiscounts = Discount::where('active', true)
            ->whereNotNull('valid_until')
            ->where('valid_until', '<', $now)
            ->get();

        if ($expiredDiscounts->isEmpty()) {
            $this->info('No expired discounts to deactivate.');
            return 0;
        }

        foreach ($expiredDiscounts as $discount) {
            $discount->active = false;
            $discount->save();
            $this->line("Deactivated discount: '{$discount->name}' (Code: {$discount->code})");
        }

        $this->info("Successfully deactivated {$expiredDiscounts->count()} expired discounts.");
        return 0;
    }
}
