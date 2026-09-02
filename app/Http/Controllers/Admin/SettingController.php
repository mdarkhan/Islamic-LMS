<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditLogger;
use App\Services\Settings\SettingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Admin settings for the public modules: general, Zakat reference rates, and the
 * calendar. Secrets (SMTP password, APP_KEY, DB credentials) live in the environment and
 * are NEVER editable here (brief §44) — the mail section only reports configured/not.
 */
class SettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function edit(): View
    {
        return view('admin.settings.edit', [
            'general' => $this->settings->group('general'),
            'about' => $this->settings->group('about'),
            'zakat' => $this->settings->group('zakat'),
            'calendar' => $this->settings->group('calendar'),
            'goldUpdatedAt' => $this->settings->updatedAt('gold_price_per_gram'),
            'silverUpdatedAt' => $this->settings->updatedAt('silver_price_per_gram'),
            // Diagnostic only — never the credential values themselves.
            'mail' => [
                'configured' => filled(config('mail.mailer')) && filled(config('mail.mailers.smtp.host')),
                'from' => filled(config('mail.from.address')),
                'ustaz' => filled($this->settings->get('ustaz_email')) || filled(config('mail.ustaz_email')),
                'contact' => filled($this->settings->get('contact_email')) || filled($this->settings->get('ustaz_email')) || filled(config('mail.ustaz_email')),
            ],
        ]);
    }

    public function updateGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'site_title' => ['required', 'string', 'max:150'],
            'site_tagline' => ['nullable', 'string', 'max:250'],
            'telegram_url' => ['nullable', 'url', 'max:250'],
            'whatsapp_url' => ['nullable', 'url', 'max:250'],
            'facebook_page_url' => ['nullable', 'url', 'max:250'],
            'facebook_group_url' => ['nullable', 'url', 'max:250'],
            'ustaz_email' => ['nullable', 'email', 'max:190'],
            'contact_email' => ['nullable', 'email', 'max:190'],
        ]);

        $this->settings->set($data, $request->user());
        $this->audit->log('settings.general_updated', null, after: ['keys' => array_keys($data)]);

        return back()->with('success', __('settings.saved'));
    }

    public function updateAbout(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'about_bio' => ['nullable', 'string', 'max:5000'],
            'about_photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $oldPhoto = $this->settings->get('about_photo');
        $values = ['about_bio' => $data['about_bio'] ?? ''];

        if ($request->hasFile('about_photo')) {
            $values['about_photo'] = $request->file('about_photo')->store('about', 'public');
        }

        $this->settings->set($values, $request->user());

        // Only remove the old file once the new one is safely saved, and only if it changed.
        if ($oldPhoto && ($values['about_photo'] ?? $oldPhoto) !== $oldPhoto) {
            Storage::disk('public')->delete($oldPhoto);
        }

        $this->audit->log('settings.about_updated', null, after: ['keys' => array_keys($values)]);

        return back()->with('success', __('settings.saved'));
    }

    public function updateZakat(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'gold_price_per_gram' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'silver_price_per_gram' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'nisab_basis' => ['required', 'in:gold,silver'],
            'currency_label' => ['required', 'string', 'max:10'],
        ]);

        $this->settings->set($data, $request->user());
        // Audited, but rate values are not secrets — record which keys changed.
        $this->audit->log('settings.zakat_updated', null, after: [
            'nisab_basis' => $data['nisab_basis'],
            'currency_label' => $data['currency_label'],
            'rates_set' => filled($data['gold_price_per_gram']) || filled($data['silver_price_per_gram']),
        ]);

        return back()->with('success', __('settings.saved'));
    }

    public function updateCalendar(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'calendar_latitude' => ['required', 'numeric', 'between:-90,90'],
            'calendar_longitude' => ['required', 'numeric', 'between:-180,180'],
            'calendar_timezone' => ['required', 'timezone'],
            // Bounded to keep an ordinary admin from setting an extreme offset (brief §39).
            'hijri_offset_days' => ['required', 'integer', 'in:-1,0,1'],
        ]);

        $this->settings->set($data, $request->user());
        $this->audit->log('settings.calendar_updated', null, after: [
            'hijri_offset_days' => $data['hijri_offset_days'],
            'timezone' => $data['calendar_timezone'],
        ]);

        return back()->with('success', __('settings.saved'));
    }
}
