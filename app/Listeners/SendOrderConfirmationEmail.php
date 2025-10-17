<?php

namespace App\Listeners;

use App\Events\NewOrderReceived;
use App\Mail\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail
{
    /**
     * Handle the event.
     *
     * @param  NewOrderReceived  $event
     * @return void
     */
    public function handle(NewOrderReceived $event)
    {
        $transaction = $event->transaction;
        
        // Only send email for online payments if not already sent
        // For cash payments, email is sent immediately in the controller
        // Check all status values that indicate successful payment
        if ($transaction->payment_method === 'midtrans' && 
           ($transaction->payment_status === 'paid' || 
            $transaction->payment_status === 'settlement' || 
            $transaction->payment_status === 'capture')) {
            try {
                Mail::to($transaction->customer_email)
                    ->send(new OrderConfirmation($transaction));
                
                Log::info('Order confirmation email sent for online payment', [
                    'transaction_id' => $transaction->id,
                    'kode_transaksi' => $transaction->kode_transaksi
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send order confirmation email', [
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}