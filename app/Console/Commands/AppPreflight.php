<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AppPreflight extends Command
{
    protected $signature = 'app:preflight {--production : Enforce production-only configuration}';

    protected $description = 'Check the PHP runtime, writable paths, and production configuration';

    public function handle(): int
    {
        $strict = (bool) $this->option('production');
        $checks = [
            $this->check('PHP >= 8.3', PHP_VERSION_ID >= 80300, true, PHP_VERSION),
            $this->extension('pdo_mysql', true),
            $this->extension('openssl', true),
            $this->extension('fileinfo', true),
            $this->extension('tokenizer', true),
            $this->extension('dom', true),
            $this->extension('xmlreader', true),
            $this->extension('zip', true),
            $this->extension('bcmath', true),
            $this->extension('json', true),
            $this->extension('ctype', false),
            $this->extension('mbstring', false),
            $this->extension('intl', false),
            $this->check('Normalizer available', class_exists(\Normalizer::class), true),
            $this->check('storage writable', is_writable(storage_path()), true),
            $this->check('bootstrap/cache writable', is_writable(base_path('bootstrap/cache')), true),
            $this->check('APP_KEY configured', filled(config('app.key')), true),
            $this->check('MySQL selected', config('database.default') === 'mysql', true, (string) config('database.default')),
            $this->check('Asia/Dhaka timezone', config('app.timezone') === 'Asia/Dhaka', true, (string) config('app.timezone')),
        ];

        if ($strict) {
            array_push($checks,
                $this->check('APP_ENV=production', app()->environment('production'), true, app()->environment()),
                $this->check('APP_DEBUG=false', ! config('app.debug'), true),
                $this->check('APP_URL uses HTTPS', str_starts_with((string) config('app.url'), 'https://'), true),
                $this->check('Secure session cookie', (bool) config('session.secure'), true),
                $this->check('SMTP mailer', config('mail.default') === 'smtp', true, (string) config('mail.default')),
                $this->check('SMTP host configured', filled(config('mail.mailers.smtp.host')), true),
                $this->check('SMTP username configured', filled(config('mail.mailers.smtp.username')), true),
                $this->check('SMTP password configured', filled(config('mail.mailers.smtp.password')), true),
                $this->check('MAIL_FROM_ADDRESS valid', filter_var(config('mail.from.address'), FILTER_VALIDATE_EMAIL) !== false, true),
                $this->check('USTAZ_EMAIL valid', filter_var(config('mail.ustaz_email'), FILTER_VALIDATE_EMAIL) !== false, true),
            );
        }

        $this->table(['Check', 'Status', 'Required', 'Detail'], array_map(fn ($check) => [
            $check['name'], $check['ok'] ? 'PASS' : ($check['required'] ? 'FAIL' : 'WARN'),
            $check['required'] ? 'yes' : 'recommended', $check['detail'],
        ], $checks));

        return collect($checks)->contains(fn ($check) => $check['required'] && ! $check['ok'])
            ? self::FAILURE : self::SUCCESS;
    }

    /** @return array{name:string,ok:bool,required:bool,detail:string} */
    private function extension(string $name, bool $required): array
    {
        return $this->check("ext-{$name}", extension_loaded($name), $required);
    }

    /** @return array{name:string,ok:bool,required:bool,detail:string} */
    private function check(string $name, bool $ok, bool $required, string $detail = ''): array
    {
        return compact('name', 'ok', 'required', 'detail');
    }
}
