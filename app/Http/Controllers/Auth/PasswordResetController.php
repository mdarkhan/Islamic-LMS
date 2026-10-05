<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Self-service password reset by email, for accounts that have one. The link goes ONLY
 * to the address already on the account — never to an address typed into this form —
 * and the response is identical whether or not the account exists, has an email, or is
 * suspended, so the form cannot be used to probe for accounts.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $data = $request->validate(['identifier' => ['required', 'string', 'max:190']]);

        $key = 'password-reset:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return back()->withErrors(['identifier' => __('auth.reset_throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }
        RateLimiter::hit($key, 3600);

        $user = $this->resolveUser($data['identifier']);

        if ($user && $user->isActive() && filled($user->email)) {
            try {
                PasswordBroker::broker()->sendResetLink(['email' => $user->email]);
            } catch (\Throwable $e) {
                // Never tell the visitor (that would confirm the account); never log the address.
                Log::warning('Password reset mail could not be sent', ['user_id' => $user->id, 'error' => $e::class]);
            }
        }

        return back()->with('status', __('auth.reset_link_sent'));
    }

    public function form(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email', '')]);
    }

    public function reset(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate(['token' => ['required', 'string'], 'email' => ['required', 'email']]);

        $account = User::query()->where('email', $request->input('email'))->first();
        $request->validate([
            'password' => ['required', 'confirmed', $account?->passwordRule() ?? Password::min(6)],
        ]);

        $invalid = fn () => back()->withInput($request->only('email'))
            ->withErrors(['email' => __('auth.reset_invalid')]);

        if ($account && ! $account->isActive()) {
            return $invalid();
        }

        $status = PasswordBroker::broker()->reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use ($audit) {
                $user->forceFill(['password' => Hash::make($password), 'force_password_change' => false])->save();
                $user->rotateRememberToken();
                DB::table('sessions')->where('user_id', $user->id)->delete();   // signed-in copies of the old password die

                $audit->log('password.reset_by_email', $user, actor: $user);
                event(new PasswordReset($user));
            }
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            return $invalid();
        }

        return redirect()->route('login')->with('success', __('auth.reset_done'));
    }

    private function resolveUser(string $identifier): ?User
    {
        if (str_contains($identifier, '@')) {
            return User::query()->where('email', trim($identifier))->first();
        }

        return User::query()->where('roll', User::normaliseRoll($identifier))->first();
    }
}
