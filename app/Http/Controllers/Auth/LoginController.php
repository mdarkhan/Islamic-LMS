<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * One login screen for both audiences. An identifier containing "@" is treated as
 * a staff email; anything else is a student roll (Bengali or Latin digits, via
 * User::normaliseRoll).
 *
 * Credentials are verified before any account-status message is shown, so a
 * suspended-account notice never leaks the existence of an account to someone who
 * does not already know its password.
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureNotRateLimited($request, $data['identifier']);

        $user = $this->resolveUser($data['identifier']);

        // Constant-ish work whether or not the user exists: still run a hash check
        // against a dummy so timing does not trivially reveal account existence.
        $passwordOk = $user
            ? Hash::check($data['password'], $user->password)
            : Hash::check($data['password'], '$2y$12$'.str_repeat('x', 53));

        if (! $user || ! $passwordOk) {
            RateLimiter::hit($this->throttleKey($request, $data['identifier']));

            throw ValidationException::withMessages([
                'identifier' => 'রোল/ইমেইল অথবা পাসওয়ার্ড সঠিক নয়।',
            ]);
        }

        // Credentials verified — now it is safe to explain a blocked account.
        if (! $user->isActive()) {
            $message = $user->status === User::STATUS_SUSPENDED
                ? 'আপনার অ্যাকাউন্টটি সাময়িকভাবে স্থগিত রয়েছে। অনুগ্রহ করে কর্তৃপক্ষের সাথে যোগাযোগ করুন।'
                : 'আপনার অ্যাকাউন্টটি নিষ্ক্রিয় রয়েছে। অনুগ্রহ করে কর্তৃপক্ষের সাথে যোগাযোগ করুন।';

            throw ValidationException::withMessages(['identifier' => $message]);
        }

        RateLimiter::clear($this->throttleKey($request, $data['identifier']));

        Auth::login($user, remember: $request->boolean('remember'));
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended($this->homeFor($user));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function resolveUser(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::query()->where('email', $identifier)->first();
        }

        return User::query()->where('roll', User::normaliseRoll($identifier))->first();
    }

    private function homeFor(User $user): string
    {
        return $user->isAdmin() ? route('admin.dashboard') : route('student.dashboard');
    }

    private function ensureNotRateLimited(Request $request, string $identifier): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($request, $identifier), self::MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey($request, $identifier));

        throw ValidationException::withMessages([
            'identifier' => "অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে {$seconds} সেকেন্ড পর আবার চেষ্টা করুন।",
        ]);
    }

    /**
     * Canonicalise the identifier BEFORE building the throttle key so a single
     * account shares one bucket regardless of representation: "১০১" and "101" both
     * normalise to the same roll, and "Boss@Example.test " to the same email. Without
     * this, one account would get separate throttle buckets per spelling.
     */
    private function throttleKey(Request $request, string $identifier): string
    {
        $canonical = str_contains($identifier, '@')
            ? mb_strtolower(trim($identifier))
            : (User::normaliseRoll($identifier) ?? trim($identifier));

        return 'login:'.$canonical.'|'.$request->ip();
    }
}
