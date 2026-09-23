<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactMessage;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Public "Contact" form — email only, no persistence (same contract as Ask Ustaz,
 * CLAUDE.md #1's philosophy extended to this sibling public form).
 *
 * Flow: validate → anti-spam → Mail → configured recipient → success. The message is
 * used only to compose the outgoing mail; it is never written to a table, an audit log,
 * or the application log. If mail fails, the user is told plainly to retry — success is
 * NEVER claimed on a failed send.
 */
class ContactController extends Controller
{
    /** Reject a submission completed impossibly fast — a simple bot heuristic. */
    private const MIN_FILL_SECONDS = 3;

    private const SESSION_KEY = 'contact_started_at';

    public function __construct(private readonly SettingService $settings) {}

    public function show(): View
    {
        // Recorded server-side, not trusted from the client: a bot can lie about a
        // submitted "started_at" (or simply omit it), but it cannot fake how long ago
        // ITS OWN session actually reached this page.
        session()->put(self::SESSION_KEY, now()->getTimestamp());

        return view('public.contact');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        // Honeypot + minimum fill time: silently accept-looking, but send nothing.
        if ($this->looksLikeSpam($request)) {
            return redirect()->route('contact.show')->with('success', __('contact.sent'));
        }

        // Admin-set recipient wins; fall back to the Ask Ustaz recipient, then the
        // environment, so Contact still works before it is explicitly configured.
        $recipient = $this->settings->get('contact_email')
            ?: $this->settings->get('ustaz_email')
            ?: config('mail.ustaz_email');

        if (empty($recipient)) {
            // Never log the message — only that the destination is unconfigured.
            Log::warning('Contact: recipient is not configured (contact_email/ustaz_email/USTAZ_EMAIL); message not sent.');

            return back()->withInput($this->safeInput($request))->with('error', __('contact.failed'));
        }

        try {
            Mail::to($recipient)->send(new ContactMessage(
                name: $request->string('name')->toString(),
                email: $request->string('email')->toString(),
                mobile: $request->input('mobile'),
                topic: $request->input('subject'),
                message: $request->string('message')->toString(),
                submittedAt: CarbonImmutable::now()->setTimezone('Asia/Dhaka')->format('d/m/Y H:i'),
            ));
        } catch (\Throwable $e) {
            Log::error('Contact mail delivery failed: '.$e->getMessage());

            return back()->withInput($this->safeInput($request))->with('error', __('contact.failed'));
        }

        return redirect()->route('contact.show')->with('success', __('contact.sent'));
    }

    private function looksLikeSpam(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return true;   // honeypot filled
        }

        $startedAt = (int) session(self::SESSION_KEY, 0);

        return $startedAt > 0 && (now()->getTimestamp() - $startedAt) < self::MIN_FILL_SECONDS;
    }

    /**
     * Fields safe to flash back for a retry — deliberately EXCLUDES the message, so no
     * message content ever lands in the (MySQL) session store.
     *
     * @return array<string, mixed>
     */
    private function safeInput(Request $request): array
    {
        return $request->only(['name', 'email', 'mobile', 'subject']);
    }
}
