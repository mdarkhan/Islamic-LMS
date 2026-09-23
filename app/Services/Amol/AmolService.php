<?php

namespace App\Services\Amol;

use App\Models\Amol;
use App\Models\AmolDayNote;
use App\Models\AmolEntry;
use App\Models\User;
use App\Services\Notifications\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of amol_entries / amol_day_notes.
 *
 * The hard rule: a checkmark may only ever be written for TODAY. `toggle()` takes no
 * date argument at all — it always resolves the server's current Dhaka date itself,
 * the same "never trust a client-supplied ... elapsed time" posture the exam timer
 * uses. Once a day has passed there is no code path that can still mark it, by
 * construction, not by a check that could be forgotten or bypassed.
 *
 * A day's NOTE is different: the ustaz reviews after the fact, so saveNote() accepts
 * any date.
 */
class AmolService
{
    public function __construct(private readonly NotificationService $notifications) {}

    /** Today's calendar date in the app timezone (Asia/Dhaka) — the only editable date. */
    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now()->startOfDay();
    }

    /**
     * The checklist for one student on one date, each item merged with whether it was
     * done — every active Amol appears even if the student has no row yet for that day.
     *
     * @return Collection<int, array{amol:Amol, is_done:bool}>
     */
    public function checklistFor(User $student, CarbonImmutable $date): Collection
    {
        $entries = AmolEntry::query()
            ->where('user_id', $student->getKey())
            ->whereDate('date', $date)
            ->get()
            ->keyBy('amol_id');

        return Amol::query()->active()->ordered()->get()
            ->map(fn (Amol $amol) => [
                'amol' => $amol,
                'is_done' => (bool) ($entries->get($amol->getKey())?->is_done),
            ]);
    }

    /**
     * Flip one deed for TODAY only. The caller (the controller) is responsible for
     * gating on $amol->is_active — this method trusts it has already been checked.
     */
    public function toggle(User $student, Amol $amol): AmolEntry
    {
        $today = $this->today();

        return DB::transaction(function () use ($student, $amol, $today) {
            $entry = AmolEntry::query()->lockForUpdate()->firstOrNew([
                'user_id' => $student->getKey(),
                'amol_id' => $amol->getKey(),
                'date' => $today->toDateString(),
            ]);

            $entry->is_done = ! $entry->is_done;
            $entry->save();

            return $entry;
        });
    }

    /** How many of today's active items are checked — for the progress line. */
    public function todayProgress(User $student): array
    {
        $checklist = $this->checklistFor($student, $this->today());

        return [
            'done' => $checklist->where('is_done', true)->count(),
            'total' => $checklist->count(),
        ];
    }

    public function noteFor(User $student, CarbonImmutable $date): ?AmolDayNote
    {
        return AmolDayNote::query()
            ->where('user_id', $student->getKey())
            ->whereDate('date', $date)
            ->first();
    }

    /**
     * The ustaz's comment on a student's day. Any date — review happens after the fact.
     * `seen_at` is always reset to null, so a first-time comment AND a later revision
     * both surface as new — pings both the "আমলনামা" sidebar badge (via seen_at) and
     * the bell-icon feed (via NotificationService, upserted per day-note so re-editing
     * refreshes one entry instead of piling up).
     */
    public function saveNote(User $student, CarbonImmutable $date, string $note, User $admin): AmolDayNote
    {
        $note = trim($note);

        $row = DB::transaction(function () use ($student, $date, $note, $admin) {
            $row = AmolDayNote::query()->firstOrNew([
                'user_id' => $student->getKey(),
                'date' => $date->toDateString(),
            ]);

            $row->fill([
                'note' => $note,
                'commented_by' => $admin->getKey(),
                'commented_at' => now(),
                'seen_at' => null,
            ])->save();

            return $row;
        });

        $this->notifications->notify(
            $student,
            'amol_note',
            'উস্তায আপনার আমলনামায় মন্তব্য করেছেন',
            $note,
            route('student.amol.index', ['date' => $date->toDateString()]),
            'amol_day_note',
            $row->getKey(),
        );

        return $row;
    }

    /** Unseen day-notes across every date — the "আমলনামা" sidebar badge. */
    public function unseenNoteCountFor(User $student): int
    {
        return AmolDayNote::query()->where('user_id', $student->getKey())->unseen()->count();
    }

    /** Viewing a date with a note is what clears it — same pattern as opening a message thread. */
    public function markNoteSeen(AmolDayNote $note): void
    {
        if ($note->seen_at === null) {
            $note->forceFill(['seen_at' => now()])->save();
        }
    }
}
