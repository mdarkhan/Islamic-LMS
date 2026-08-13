<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    /**
     * Switch the interface language. Persisted to the user when signed in, and always
     * to the session so a guest's choice survives until (and through) login.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'locale' => ['required', Rule::in(SetLocale::SUPPORTED)],
        ]);

        $request->session()->put('locale', $data['locale']);

        if ($user = $request->user()) {
            $user->forceFill(['locale' => $data['locale']])->save();
        }

        return back();
    }
}
