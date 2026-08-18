@php
    $en = app()->getLocale() === 'en';
    $old = $old ?? [];
    $result = $result ?? null;
    $val = fn ($k) => old($k, $old[$k] ?? '');
    $boot = [
        'currency' => $currency,
        'rate' => (float) $constants['rate'],
        'goldNisab' => $goldNisab,       // server-computed strings (or null)
        'silverNisab' => $silverNisab,
        'goldRate' => $goldRate,
        'silverRate' => $silverRate,
        'vori' => (float) $constants['vori_to_gram'],
        'bnDigits' => ! $en,
        'basis' => old('basis', $old['basis'] ?? $basis),
    ];
    $server = $result ?? null;
@endphp

<x-layout.public :title="__('zakat.heading')" :description="__('zakat.intro')" :canonical="route('zakat.index')">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 py-8 sm:py-12"
         x-data="zakatCalc(@js($boot))">
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-black text-ink">{{ __('zakat.heading') }}</h1>
            <p class="text-muted mt-1">{{ __('zakat.intro') }}</p>
        </header>

        <form method="POST" action="{{ route('zakat.calculate') }}" class="grid gap-6 lg:grid-cols-[1.4fr_1fr] items-start">
            @csrf
            <div class="space-y-5">
                {{-- Assets --}}
                <x-ui.card>
                    <h2 class="font-bold text-ink mb-3">{{ __('zakat.assets') }}</h2>
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach (['cash','bank','gold_value','silver_value','inventory','investments','receivable','other'] as $key)
                            <x-ui.field :label="__('zakat.'.$key)" name="{{ $key }}">
                                <x-ui.input type="number" step="0.01" min="0" name="{{ $key }}" x-model.number="v.{{ $key }}" value="{{ $val($key) }}" placeholder="0" />
                            </x-ui.field>
                        @endforeach
                    </div>

                    {{-- Weight → value helper --}}
                    <details class="mt-4 rounded-xl border border-line p-3">
                        <summary class="cursor-pointer text-sm font-semibold text-brand">{{ __('zakat.metal_helper') }}</summary>
                        <div class="mt-3 grid sm:grid-cols-2 gap-4">
                            @foreach (['gold' => 'gold_value', 'silver' => 'silver_value'] as $metal => $target)
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold text-muted uppercase">{{ __('zakat.'.$target) }}</p>
                                    <div class="flex gap-2">
                                        <input type="number" step="0.001" min="0" x-model.number="w.{{ $metal }}_g" placeholder="{{ __('zakat.weight_grams') }}" class="w-full rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm" />
                                        <input type="number" step="0.001" min="0" x-model.number="w.{{ $metal }}_v" placeholder="{{ __('zakat.weight_vori') }}" class="w-full rounded-lg border border-line bg-surface-raised px-3 py-2 text-sm" />
                                    </div>
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="text-muted tabular-nums" x-text="cur(metalValue('{{ $metal }}'))"></span>
                                        <button type="button" class="text-brand font-semibold" @click="v.{{ $target }} = metalValue('{{ $metal }}')">{{ __('zakat.apply_value') }}</button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        <p class="text-xs text-muted mt-2">{{ __('zakat.vori_note') }}</p>
                    </details>
                </x-ui.card>

                {{-- Liabilities + basis --}}
                <x-ui.card>
                    <div class="grid sm:grid-cols-2 gap-3">
                        <x-ui.field :label="__('zakat.liabilities')" name="liabilities" :hint="__('zakat.liabilities_hint')">
                            <x-ui.input type="number" step="0.01" min="0" name="liabilities" x-model.number="v.liabilities" value="{{ $val('liabilities') }}" placeholder="0" />
                        </x-ui.field>
                        <x-ui.field :label="__('zakat.basis')" name="basis">
                            <x-ui.select name="basis" x-model="basis">
                                <option value="silver">{{ __('zakat.basis_silver') }}</option>
                                <option value="gold">{{ __('zakat.basis_gold') }}</option>
                            </x-ui.select>
                        </x-ui.field>
                    </div>
                    <x-ui.button type="submit" class="mt-4 w-full sm:w-auto">{{ __('zakat.calculate') }}</x-ui.button>
                </x-ui.card>
            </div>

            {{-- Result (Alpine live; falls back to the server result without JS) --}}
            <div class="lg:sticky lg:top-20 space-y-4">
                <x-ui.card class="bg-brand-tint/30">
                    <h2 class="font-bold text-ink mb-3">{{ __('zakat.result') }}</h2>
                    <dl class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><dt class="text-muted">{{ __('zakat.total_assets') }}</dt><dd class="tabular-nums font-semibold" x-text="cur(gross)">{{ $server['currency'] ?? '৳' }} {{ $server['gross'] ?? '0.00' }}</dd></div>
                        <div class="flex justify-between"><dt class="text-muted">{{ __('zakat.deductible') }}</dt><dd class="tabular-nums" x-text="'− ' + cur(deductible)">− {{ $server['currency'] ?? '৳' }} {{ $server['deductible'] ?? '0.00' }}</dd></div>
                        <div class="flex justify-between border-t border-line pt-1.5"><dt class="text-ink font-semibold">{{ __('zakat.net') }}</dt><dd class="tabular-nums font-bold" x-text="cur(net)">{{ $server['currency'] ?? '৳' }} {{ $server['net'] ?? '0.00' }}</dd></div>
                    </dl>

                    <div class="mt-3 pt-3 border-t border-line text-sm">
                        <div class="flex justify-between"><dt class="text-muted" x-text="nisabLabel()">{{ __('zakat.nisab_value', ['basis' => $server ? __('zakat.basis_'.$server['basis']) : __('zakat.basis_silver')]) }}</dt><dd class="tabular-nums" x-text="nisab === null ? '—' : cur(nisab)">{{ $server && $server['nisab'] ? $server['currency'].' '.$server['nisab'] : '—' }}</dd></div>
                        <p class="mt-2" x-show="ratesMissing()" x-cloak><span class="text-amber-600 text-xs" x-text="ratesMissingText()"></span></p>
                        <p class="mt-2 font-semibold" :class="reached() ? 'text-brand' : 'text-muted'" x-text="reached() ? @js(__('zakat.reached')) : @js(__('zakat.below'))">{{ $server ? ($server['nisab_reached'] ? __('zakat.reached') : __('zakat.below')) : __('zakat.below') }}</p>
                    </div>

                    <div class="mt-4 rounded-xl bg-card border border-line p-4 text-center">
                        <p class="text-xs text-muted">{{ __('zakat.due') }}</p>
                        <p class="text-3xl font-black text-brand tabular-nums" x-text="cur(due)">{{ $server['currency'] ?? '৳' }} {{ $server['due'] ?? '0.00' }}</p>
                        <p class="text-xs text-muted mt-1">{{ __('zakat.rate_line') }}</p>
                    </div>

                    @php $rateWhen = $goldUpdatedAt ?? $silverUpdatedAt; @endphp
                    @if ($rateWhen)
                        <p class="text-xs text-muted mt-3">{{ __('zakat.rates_updated', ['when' => $en ? $rateWhen->format('j M Y') : bn($rateWhen->format('d/m/Y'))]) }}</p>
                    @endif
                </x-ui.card>

                <p class="text-xs text-muted leading-relaxed px-1">{{ __('zakat.disclaimer') }}</p>
            </div>
        </form>
    </div>

    <script>
        window.zakatCalc = function (boot) {
            return {
                v: { cash: {{ (float) $val('cash') ?: 0 }}, bank: {{ (float) $val('bank') ?: 0 }}, gold_value: {{ (float) $val('gold_value') ?: 0 }}, silver_value: {{ (float) $val('silver_value') ?: 0 }}, inventory: {{ (float) $val('inventory') ?: 0 }}, investments: {{ (float) $val('investments') ?: 0 }}, receivable: {{ (float) $val('receivable') ?: 0 }}, other: {{ (float) $val('other') ?: 0 }}, liabilities: {{ (float) $val('liabilities') ?: 0 }} },
                w: { gold_g: 0, gold_v: 0, silver_g: 0, silver_v: 0 },
                basis: boot.basis,
                _b: boot,

                num(x) { const n = parseFloat(x); return isNaN(n) || n < 0 ? 0 : n; },
                get gross() { return ['cash','bank','gold_value','silver_value','inventory','investments','receivable','other'].reduce((s,k)=>s+this.num(this.v[k]),0); },
                get deductible() { return Math.min(this.num(this.v.liabilities), this.gross); },
                get net() { return Math.max(0, this.gross - this.deductible); },
                get nisab() { const n = this.basis === 'gold' ? this._b.goldNisab : this._b.silverNisab; return n === null ? null : parseFloat(n); },
                reached() { return this.nisab !== null && this.net >= this.nisab; },
                get due() { return this.reached() ? this.net * this._b.rate : 0; },
                ratesMissing() { return this.nisab === null; },

                metalValue(metal) {
                    const rate = this.num(metal === 'gold' ? this._b.goldRate : this._b.silverRate);
                    const grams = this.num(this.w[metal + '_g']) + this.num(this.w[metal + '_v']) * this._b.vori;
                    return Math.round(grams * rate * 100) / 100;
                },

                nisabLabel() {
                    const basisTxt = this.basis === 'gold' ? @js(__('zakat.basis_gold')) : @js(__('zakat.basis_silver'));
                    return @js(__('zakat.nisab_value', ['basis' => '__B__'])).replace('__B__', basisTxt);
                },
                ratesMissingText() {
                    const basisTxt = this.basis === 'gold' ? @js(__('zakat.basis_gold')) : @js(__('zakat.basis_silver'));
                    return @js(__('zakat.rates_missing', ['basis' => '__B__'])).replace('__B__', basisTxt);
                },

                cur(n) {
                    let s = (Math.round(n * 100) / 100).toFixed(2);
                    if (this._b.bnDigits) { const m = {'0':'০','1':'১','2':'২','3':'৩','4':'৪','5':'৫','6':'৬','7':'৭','8':'৮','9':'৯'}; s = s.replace(/[0-9]/g, c => m[c]); }
                    return this._b.currency + ' ' + s;
                },
            };
        };
    </script>
</x-layout.public>
