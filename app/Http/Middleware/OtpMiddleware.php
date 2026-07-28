<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OtpMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip OTP routes
        if ($request->is('otp', 'otp/*', 'shared', 'shared/*')) {
            return $next($request);
        }

        // Check if OTP verified and not expired
        if (
            session('otp_verified') &&
            session('otp_expires_at') &&
            now()->lt(session('otp_expires_at'))
        ) {
            return $next($request);
        }

        // Clear expired session
        session()->forget(['otp_verified', 'otp_expires_at']);

        return redirect()->route('otp.form');
    }
}
