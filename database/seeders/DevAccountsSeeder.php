<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use App\Services\Points\PointService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Local development accounts only. Never runs in production.
 */
class DevAccountsSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DevAccountsSeeder skipped in production.');

            return;
        }

        $points = app(PointService::class);

        // Clean up legacy admin email if present
        User::query()->where('email', 'admin@masudalimi.test')->update(['email' => 'admin']);

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin'],
            [
                'name' => 'সুপার অ্যাডমিন',
                'password' => Hash::make('password'),
                'status' => User::STATUS_ACTIVE,
            ],
        );
        $admin->assignRole(Role::SUPER_ADMIN);

        $demo = [
            ['১০১', 'আব্দুল্লাহ আল মামুন', 'active', 5],
            ['১০২', 'ফাতিমা খাতুন', 'active', 3],
            ['১০৩', 'মুহাম্মদ ইব্রাহিম', 'active', 0],
            ['১০৪', 'আয়েশা সিদ্দিকা', 'suspended', 2],
            ['১০৫', 'উমর ফারুক', 'active', 8],
            ['১০৬', 'খাদিজা বেগম', 'archived', 0],
        ];

        foreach ($demo as [$roll, $name, $status, $pts]) {
            $student = User::query()->updateOrCreate(
                ['roll' => User::normaliseRoll($roll)],
                [
                    'name' => $name,
                    'guardian_name' => 'অভিভাবক',
                    'password' => Hash::make('password'),
                    'status' => $status,
                ],
            );
            $student->assignRole(Role::STUDENT);

            if ($pts > 0 && $points->ledgerBalance($student) === 0) {
                $points->credit($student, $pts, reason: 'প্রাথমিক পয়েন্ট', performedBy: $admin);
            }
        }

        $this->command?->info('Dev accounts: admin / password, students roll ১০১–১০৬ / password.');
    }
}
