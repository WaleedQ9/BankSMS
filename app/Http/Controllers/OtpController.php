<?php

namespace App\Http\Controllers;

use App\Services\TelegramService;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function showForm()
    {
        $otpSent = session('otp_sent_at') && (time() - session('otp_sent_at')) <= 60;
        return view('otp.verify', compact('otpSent'));
    }

    public function verify(Request $request)
    {
        $request->validate(['code' => 'required|string']);

        $storedCode = session('otp_code');
        $sentAt = session('otp_sent_at');
        $inputCode = $request->input('code');

        if (!$storedCode || $inputCode !== $storedCode) {
            return back()->with('error', 'الرمز غير صحيح');
        }

        // Check if OTP expired (30 seconds)
        if (!$sentAt || (time() - $sentAt) > 30) {
            session()->forget(['otp_code', 'otp_sent_at']);
            return back()->with('error', 'انتهت صلاحية الرمز، أعد الإرسال');
        }

        // Mark as verified for 24 hours
        session([
            'otp_verified' => true,
            'otp_expires_at' => now()->addDays(30),
        ]);
        session()->forget(['otp_code', 'otp_sent_at']);

        return redirect()->route('dashboard');
    }

    public function logout()
    {
        session()->forget(['otp_verified', 'otp_expires_at', 'otp_code', 'otp_sent_at']);
        return redirect()->route('otp.form');
    }

    public function resend()
    {
        $this->generateAndSend();
        return back()->with('success', 'تم إرسال رمز جديد');
    }

    private function generateAndSend(): void
    {
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        session([
            'otp_code' => $code,
            'otp_sent_at' => time(),
        ]);

        try {
            app(TelegramService::class)->sendMessage($code);
        } catch (\Exception $e) {
            // If telegram fails, still show the form
        }
    }
}
