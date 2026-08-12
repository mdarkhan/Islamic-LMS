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

            ['key' => 'site_title', 'value' => 'মাসউদ আলিমী', 'type' => 'string', 'group' => 'general'],
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
