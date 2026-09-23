<?php

namespace Database\Seeders;

use App\Models\Amol;
use Illuminate\Database\Seeder;

/**
 * The fixed daily checklist, transcribed in order from "দৈনন্দিন আমলের রুটিন" (Shapla
 * Housing Baitush Sharaf Samajkallyan Sangha). Keys are stable identifiers; `label` is
 * the exact Bengali text shown to students and never localised (it is content, like a
 * lesson title — see CLAUDE.md's i18n rule).
 *
 * Re-running only updates label/order — a student's history in amol_entries never moves.
 */
class AmolSeeder extends Seeder
{
    /** [key, label, group|null] — the five daily prayers' sub-items share a group key,
     *  which the UI renders as one dropdown per prayer; everything else is ungrouped. */
    private const ITEMS = [
        ['fajr_sunnah', 'ফজরের সুন্নাত', 'fajr'],
        ['fajr_prayer', 'ফজরের নামাজ', 'fajr'],
        ['fajr_takbir_e_oola', 'তাকবীরে উলা', 'fajr'],
        ['zuhr_before_sunnah', 'যুহরের আগের সুন্নাত', 'zuhr'],
        ['zuhr_prayer', 'যুহরের নামাজ', 'zuhr'],
        ['zuhr_takbir_e_oola', 'তাকবীরে উলা', 'zuhr'],
        ['zuhr_after_sunnah', 'যুহরের পরের সুন্নাত', 'zuhr'],
        ['asr_prayer', 'আসরের নামাজ', 'asr'],
        ['asr_takbir_e_oola', 'তাকবীরে উলা', 'asr'],
        ['maghrib_prayer', 'মাগরিবের নামাজ', 'maghrib'],
        ['maghrib_takbir_e_oola', 'তাকবীরে উলা', 'maghrib'],
        ['maghrib_sunnah', 'মাগরিবের সুন্নাত', 'maghrib'],
        ['isha_prayer', 'এশার নামাজ', 'isha'],
        ['isha_takbir_e_oola', 'তাকবীরে উলা', 'isha'],
        ['isha_sunnah', 'এশার সুন্নাত', 'isha'],
        // Witr belongs under the Isha dropdown (prayed after Isha), not a flat item.
        ['witr_prayer', 'বিতরের নামাজ', 'isha'],
        ['teen_tasbih', 'তিন তাসবীহ', null],
        ['ayatul_kursi', 'আয়াতুল কুরসী পাঠ', null],
        ['hashr_last_ayat', 'হাশরের শেষ তিন আয়াত', null],
        ['munajat', 'মুনাজাতের আমল', null],
        ['miswak', 'মিসওয়াক এর আমল', null],
        ['perfume', 'সুগন্ধি ব্যবহার', null],
        ['morning_evening_quls', 'সকাল-সন্ধ্যা তিন কুল', null],
        ['parents_obedience', 'মা-বাবার অবাধ্য না হওয়া', null],
    ];

    public function run(): void
    {
        foreach (self::ITEMS as $order => [$key, $label, $group]) {
            Amol::query()->updateOrCreate(
                ['key' => $key],
                ['label' => $label, 'group' => $group, 'sort_order' => $order, 'is_active' => true],
            );
        }
    }
}
