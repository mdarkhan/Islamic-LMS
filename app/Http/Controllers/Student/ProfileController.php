<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('student.profile', ['user' => auth()->user()]);
    }

    /**
     * Students may edit only contact fields. Identity-critical fields — roll, name,
     * guardian, status, point balance — remain admin-authoritative.
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $user->fill($data)->save();

        return back()->with('success', 'আপনার প্রোফাইল আপডেট করা হয়েছে।');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'বর্তমান পাসওয়ার্ডটি সঠিক নয়।'])->withInput();
        }

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $user->rotateRememberToken();

        return back()->with('success', 'আপনার পাসওয়ার্ড পরিবর্তন করা হয়েছে।');
    }
}
