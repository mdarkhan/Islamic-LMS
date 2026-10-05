<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Mandatory password change for a student holding an admin-issued temporary
 * password. The EnsurePasswordChanged middleware forces every protected page here
 * until force_password_change is cleared.
 */
class PasswordChangeController extends Controller
{
    public function show(): View
    {
        return view('auth.change-password');
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', $user->passwordRule()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'বর্তমান পাসওয়ার্ডটি সঠিক নয়।']);
        }

        $user->forceFill([
            'password' => Hash::make($data['password']),   // hashed; plaintext never stored or logged
            'force_password_change' => false,
        ])->save();
        $user->rotateRememberToken();

        $audit->log('password.self_changed', $user);

        return redirect()
            ->route($user->isAdmin() ? 'admin.dashboard' : 'student.dashboard')
            ->with('success', 'আপনার পাসওয়ার্ড সফলভাবে পরিবর্তন করা হয়েছে।');
    }
}
