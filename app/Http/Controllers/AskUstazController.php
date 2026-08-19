<?php

namespace App\Http\Controllers;

use App\Http\Requests\AskUstazRequest;
use App\Mail\AskUstazQuestion;
use App\Services\Settings\SettingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Public "Ask Ustaz" — email only, ABSOLUTELY no persistence (brief §16, CLAUDE.md #1).
 *
 * Flow: validate → anti-spam → Mail → configured recipient → success. The question is
 * used only to compose the outgoing message; it is never written to a table, an audit
 * log, or the application log. If mail fails, the user is told plainly to retry — success
 * is NEVER claimed on a failed send.
 */
class AskUstazController extends Controller
{
    /** Reject a submission completed impossibly fast — a simple bot heuristic. */
    private const MIN_FILL_SECONDS = 3;

    public function __construct(private readonly SettingService $settings) {}

    public function show(): View
    {
        return view('public.ask-ustaz', ['startedAt' => now()->getTimestamp()]);
    }

    public function store(AskUstazRequest $request): RedirectResponse
    {
        // Honeypot + minimum fill time: silently accept-looking, but send nothing. Not
        // tipping the bot off is better than a visible rejection.
        if ($this->looksLikeSpam($request)) {
            return redirect()->route('ask-ustaz.show')->with('success', __('ask_ustaz.sent'));
        }

        // Admin-set recipient wins; fall back to the environment (config) when unset.
        $recipient = $this->settings->get('ustaz_email') ?: config('mail.ustaz_email');
        if (empty($recipient)) {
            // Never log the question — only that the destination is unconfigured.
            Log::warning('Ask Ustaz: recipient is not configured (setting or USTAZ_EMAIL); question not sent.');

            return back()->withInput($this->safeInput($request))->with('error', __('ask_ustaz.failed'));
        }

        try {
            Mail::to($recipient)->send(new AskUstazQuestion(
                name: $request->string('name')->toString(),
                email: $request->string('email')->toString(),
                mobile: $request->input('mobile'),
                topic: $request->input('subject'),
                question: $request->string('question')->toString(),
                submittedAt: CarbonImmutable::now()->setTimezone('Asia/Dhaka')->format('d/m/Y H:i'),
            ));
        } catch (\Throwable $e) {
            // Because nothing is stored, a failed delivery means "please retry". Surface a
            // safe Bengali message; never expose SMTP internals or the question content.
            Log::error('Ask Ustaz mail delivery failed: '.$e->getMessage());

            return back()->withInput($this->safeInput($request))->with('error', __('ask_ustaz.failed'));
        }

        return redirect()->route('ask-ustaz.show')->with('success', __('ask_ustaz.sent'));
    }

    private function looksLikeSpam(Request $request): bool
    {
        if (filled($request->input('website'))) {
            return true;   // honeypot filled
        }

        $startedAt = (int) $request->input('started_at', 0);

        return $startedAt > 0 && (now()->getTimestamp() - $startedAt) < self::MIN_FILL_SECONDS;
    }

    /**
     * Fields safe to flash back for a retry — deliberately EXCLUDES the question, so no
     * question content ever lands in the (MySQL) session store.
     *
     * @return array<string, mixed>
     */
    private function safeInput(Request $request): array
    {
        return $request->only(['name', 'email', 'mobile', 'subject']);
    }
}
