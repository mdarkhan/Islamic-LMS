<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * An admin/staff member's own account. Before this, the only place a staff member could
 * ever set a password was the forced first-login change — after that they could not
 * change it themselves and had to ask a super admin for a reset.
 *
 * Deliberately no perm: gate: every admin may manage their OWN password, and the user is
 * always the authenticated one (no id in the route).
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.account', ['user' => $request->user()]);
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', $user->passwordRule()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'বর্তমান পাসওয়ার্ডটি সঠিক নয়।']);
        }

        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => __('admin.account_password_same')]);
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $user->rotateRememberToken();   // any "remember me" cookie from before the change dies with the old password

        $audit->log('password.self_changed', $user);

        return back()->with('success', __('admin.account_password_changed'));
    }
}
