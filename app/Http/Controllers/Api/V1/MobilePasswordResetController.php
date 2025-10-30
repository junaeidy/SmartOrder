<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MobilePasswordResetController extends Controller
{
    public function sendResetCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:customers,email'
        ]);

        try {
            // Generate random 6 digit code
            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            // Store the code in password_reset_tokens table
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $request->email],
                [
                    'token' => Hash::make($code),
                    'created_at' => Carbon::now()
                ]
            );

            // Send email with the code
            Mail::send('emails.reset-password', ['code' => $code], function($message) use ($request) {
                $message->to($request->email);
                $message->subject('Reset Password Code');
            });

            return response()->json([
                'message' => 'Reset password code sent to your email',
                'expires_in' => 60 // Code expires in 60 minutes
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to send reset code',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    public function verifyCodeAndReset(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:customers,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed'
        ]);

        try {
            // Get password reset record
            $reset = DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->first();

            if (!$reset) {
                return response()->json([
                    'message' => 'Invalid reset code'
                ], 400);
            }

            // Check if code is expired (60 minutes)
            if (Carbon::parse($reset->created_at)->addMinutes(60)->isPast()) {
                DB::table('password_reset_tokens')->where('email', $request->email)->delete();
                return response()->json([
                    'message' => 'Reset code has expired'
                ], 400);
            }

            // Verify the code
            if (!Hash::check($request->code, $reset->token)) {
                return response()->json([
                    'message' => 'Invalid reset code'
                ], 400);
            }

            // Update password
            $customer = Customer::where('email', $request->email)->first();
            $customer->password = Hash::make($request->password);
            $customer->save();

            // Delete the reset record
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();

            return response()->json([
                'message' => 'Password has been reset successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Unable to reset password',
                'error' => $e->getMessage()
            ], 400);
        }
    }
}