@php
    $en = app()->getLocale() === 'en';
    $when = fn ($d) => $d ? ($en ? $d->format('j M Y, g:i A') : bn($d->format('d/m/Y H:i'))) : null;
@endphp

<x-layout.admin :title="__('settings.heading')" :heading="__('settings.heading')">
    <div class="grid gap-5 lg:grid-cols-2 items-start">

        {{-- General --}}
        <x-ui.card>
            <h2 class="font-bold text-ink mb-4">{{ __('settings.general') }}</h2>
            <form method="POST" action="{{ route('admin.settings.general') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-ui.field :label="__('settings.site_title')" name="site_title" :required="true">
                    <x-ui.input name="site_title" value="{{ old('site_title', $general['site_title']) }}" required />
                </x-ui.field>
                <x-ui.field :label="__('settings.site_tagline')" name="site_tagline">
                    <x-ui.input name="site_tagline" value="{{ old('site_tagline', $general['site_tagline']) }}" />
                </x-ui.field>
                <x-ui.field :label="__('settings.telegram_url')" name="telegram_url">
                    <x-ui.input name="telegram_url" value="{{ old('telegram_url', $general['telegram_url']) }}" placeholder="https://t.me/…" />
                </x-ui.field>
                <x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Zakat --}}
        <x-ui.card>
            <h2 class="font-bold text-ink mb-1">{{ __('settings.zakat') }}</h2>
            <p class="text-xs text-muted mb-4">
                {{ ($goldUpdatedAt || $silverUpdatedAt) ? __('settings.rates_updated', ['when' => $when($goldUpdatedAt ?? $silverUpdatedAt)]) : __('settings.rates_never') }}
            </p>
            <form method="POST" action="{{ route('admin.settings.zakat') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field :label="__('settings.gold_price')" name="gold_price_per_gram">
                        <x-ui.input type="number" step="0.01" min="0" name="gold_price_per_gram" value="{{ old('gold_price_per_gram', $zakat['gold_price_per_gram']) }}" />
                    </x-ui.field>
                    <x-ui.field :label="__('settings.silver_price')" name="silver_price_per_gram">
                        <x-ui.input type="number" step="0.01" min="0" name="silver_price_per_gram" value="{{ old('silver_price_per_gram', $zakat['silver_price_per_gram']) }}" />
                    </x-ui.field>
                    <x-ui.field :label="__('settings.nisab_basis')" name="nisab_basis">
                        <x-ui.select name="nisab_basis">
                            <option value="silver" @selected($zakat['nisab_basis'] === 'silver')>{{ __('settings.basis_silver') }}</option>
                            <option value="gold" @selected($zakat['nisab_basis'] === 'gold')>{{ __('settings.basis_gold') }}</option>
                        </x-ui.select>
                    </x-ui.field>
                    <x-ui.field :label="__('settings.currency_label')" name="currency_label" :required="true">
                        <x-ui.input name="currency_label" value="{{ old('currency_label', $zakat['currency_label']) }}" required />
                    </x-ui.field>
                </div>
                <x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Calendar --}}
        <x-ui.card>
            <h2 class="font-bold text-ink mb-1">{{ __('settings.calendar') }}</h2>
            <p class="text-xs text-muted mb-4">{{ __('settings.location_hint') }}</p>
            <form method="POST" action="{{ route('admin.settings.calendar') }}" class="space-y-4">
                @csrf @method('PUT')
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.field :label="__('settings.latitude')" name="calendar_latitude" :required="true">
                        <x-ui.input type="number" step="0.0001" name="calendar_latitude" value="{{ old('calendar_latitude', $calendar['calendar_latitude']) }}" required />
                    </x-ui.field>
                    <x-ui.field :label="__('settings.longitude')" name="calendar_longitude" :required="true">
                        <x-ui.input type="number" step="0.0001" name="calendar_longitude" value="{{ old('calendar_longitude', $calendar['calendar_longitude']) }}" required />
                    </x-ui.field>
                </div>
                <x-ui.field :label="__('settings.timezone')" name="calendar_timezone" :required="true">
                    <x-ui.input name="calendar_timezone" value="{{ old('calendar_timezone', $calendar['calendar_timezone']) }}" required />
                </x-ui.field>
                <x-ui.field :label="__('settings.hijri_offset')" name="hijri_offset_days" :hint="__('settings.hijri_offset_hint')" :required="true">
                    <x-ui.select name="hijri_offset_days">
                        @foreach (['-1' => '−1', '0' => '0', '1' => '+1'] as $v => $label)
                            <option value="{{ $v }}" @selected((string) $calendar['hijri_offset_days'] === (string) $v)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>
                </x-ui.field>
                <x-ui.button type="submit">{{ __('settings.save') }}</x-ui.button>
            </form>
        </x-ui.card>

        {{-- Mail diagnostic (never reveals secrets) --}}
        <x-ui.card>
            <h2 class="font-bold text-ink mb-4">{{ __('settings.mail') }}</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('settings.mail_status') }}</dt>
                    <dd><x-ui.badge :color="$mail['configured'] ? 'success' : 'warning'">{{ $mail['configured'] ? __('settings.mail_configured') : __('settings.mail_incomplete') }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('settings.mail_from') }}</dt>
                    <dd><x-ui.badge :color="$mail['from'] ? 'success' : 'warning'">{{ $mail['from'] ? __('settings.set') : __('settings.missing') }}</x-ui.badge></dd>
                </div>
                <div class="flex items-center justify-between">
                    <dt class="text-muted">{{ __('settings.ustaz_recipient') }}</dt>
                    <dd><x-ui.badge :color="$mail['ustaz'] ? 'success' : 'warning'">{{ $mail['ustaz'] ? __('settings.set') : __('settings.missing') }}</x-ui.badge></dd>
                </div>
            </dl>
            <p class="text-xs text-muted mt-4">{{ __('settings.mail_note') }}</p>
        </x-ui.card>
    </div>
</x-layout.admin>
