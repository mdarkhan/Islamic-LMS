<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defence in depth alongside the session-row deletion and remember_token rotation that
 * suspend / archive / password-reset already perform (Admin\StudentController,
 * Admin\StaffController, User::rotateRememberToken): a suspended or archived account is
 * logged out of every request it still somehow reaches, not just refused a fresh login.
 * Without this, any future code path that forgets to drop sessions or rotate the token
 * would silently leave a blocked account with live access.
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = $user->status === User::STATUS_SUSPENDED
                ? 'আপনার অ্যাকাউন্টটি সাময়িকভাবে স্থগিত রয়েছে। অনুগ্রহ করে কর্তৃপক্ষের সাথে যোগাযোগ করুন।'
                : 'আপনার অ্যাকাউন্টটি নিষ্ক্রিয় রয়েছে। অনুগ্রহ করে কর্তৃপক্ষের সাথে যোগাযোগ করুন।';

            return redirect()->route('login')->with('error', $message);
        }

        return $next($request);
    }
}
