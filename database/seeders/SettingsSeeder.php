<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // Bangladesh moon sighting can differ from any algorithmic calendar, so
            // the displayed Hijri date carries an admin-adjustable offset (brief §31).
            ['key' => 'hijri_offset_days', 'value' => '0', 'type' => 'integer', 'group' => 'calendar'],

            // Zakat reference rates. Admin-editable; the UI shows updated_at as the
            // "last updated" stamp the brief requires.
            ['key' => 'gold_price_per_gram', 'value' => null, 'type' => 'decimal', 'group' => 'zakat'],
            ['key' => 'silver_price_per_gram', 'value' => null, 'type' => 'decimal', 'group' => 'zakat'],
            ['key' => 'nisab_basis', 'value' => 'silver', 'type' => 'string', 'group' => 'zakat'],
            ['key' => 'currency_label', 'value' => '৳', 'type' => 'string', 'group' => 'zakat'],

            // Institutional location for sunset (Hijri rollover) — Dhaka. Not the
            // visitor's location; the site uses one configured place (brief §37).
            ['key' => 'calendar_latitude', 'value' => '23.8103', 'type' => 'decimal', 'group' => 'calendar'],
            ['key' => 'calendar_longitude', 'value' => '90.4125', 'type' => 'decimal', 'group' => 'calendar'],
            ['key' => 'calendar_timezone', 'value' => 'Asia/Dhaka', 'type' => 'string', 'group' => 'calendar'],

            ['key' => 'site_title', 'value' => 'মাসউদ আলিমী', 'type' => 'string', 'group' => 'general'],
            ['key' => 'site_tagline', 'value' => 'কুরআন, তাফসির ও সীরাত শিক্ষার একটি নির্ভরযোগ্য মাধ্যম', 'type' => 'string', 'group' => 'general'],
            ['key' => 'telegram_url', 'value' => 'https://t.me/seerat2026', 'type' => 'string', 'group' => 'general'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                $setting + ['updated_at' => now()],
            );
        }
    }
}
