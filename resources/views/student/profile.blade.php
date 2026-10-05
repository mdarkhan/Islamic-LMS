<x-layout.student :title="__('profile.heading')" :heading="__('profile.heading')">
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Identity (admin-authoritative) --}}
        <x-ui.card class="lg:col-span-1">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                    <x-ui.icon name="profile" class="w-8 h-8" />
                </div>
                <h2 class="font-bold text-ink text-lg">{{ $user->name }}</h2>
                <p class="text-sm text-muted">{{ __('dashboard.roll') }}: {{ bn($user->roll) }}</p>
            </div>
            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('profile.guardian') }}</dt><dd class="text-ink font-medium text-right">{{ $user->guardian_name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">{{ __('profile.status') }}</dt><dd><x-ui.badge color="success">{{ __('profile.active') }}</x-ui.badge></dd></div>
            </dl>
            <p class="mt-4 text-xs text-muted border-t border-line pt-3">
                {{ __('profile.identity_note') }}
            </p>
        </x-ui.card>

        <div class="lg:col-span-2 space-y-6">
            {{-- Language --}}
            <x-ui.card>
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-ink">{{ __('profile.language') }}</h3>
                        <p class="text-xs text-muted mt-0.5">{{ __('profile.language_hint') }}</p>
                    </div>
                    <x-ui.locale-toggle />
                </div>
            </x-ui.card>

            {{-- Contact details --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-4">{{ __('profile.contact_info') }}</h3>
                <form method="POST" action="{{ route('student.profile.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.field :label="__('profile.email')" name="email">
                        <x-ui.input name="email" type="email" :value="old('email', $user->email)" placeholder="email@example.com" />
                    </x-ui.field>
                    <label class="flex items-start gap-2 text-sm text-ink">
                        <input type="checkbox" name="notify_by_email" value="1" @checked(old('notify_by_email', $user->notify_by_email)) class="mt-0.5 rounded border-line text-brand focus:ring-brand">
                        <span>{{ __('profile.notify_by_email') }}<span class="block text-xs text-muted">{{ __('profile.notify_by_email_hint') }}</span></span>
                    </label>
                    <x-ui.field :label="__('profile.phone')" name="phone">
                        <x-ui.input name="phone" type="text" :value="old('phone', $user->phone)" placeholder="01XXXXXXXXX" />
                    </x-ui.field>
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('ui.save') }}</x-ui.button></div>
                </form>
            </x-ui.card>

            {{-- Password --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-4">{{ __('profile.change_password') }}</h3>
                <form method="POST" action="{{ route('student.profile.password') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.field :label="__('profile.current_password')" name="current_password" required>
                        <x-ui.input name="current_password" type="password" autocomplete="current-password" />
                    </x-ui.field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-ui.field :label="__('profile.new_password')" name="password" :hint="__('profile.min_chars')" required>
                            <x-ui.input name="password" type="password" autocomplete="new-password" />
                        </x-ui.field>
                        <x-ui.field :label="__('profile.confirm')" name="password_confirmation" required>
                            <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
                        </x-ui.field>
                    </div>
                    <div class="flex justify-end"><x-ui.button type="submit">{{ __('profile.change_password') }}</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
