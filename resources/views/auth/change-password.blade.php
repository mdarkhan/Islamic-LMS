<x-layout.guest :title="__('auth.change_password')">
    <x-ui.card>
        <div class="text-center mb-6">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                <x-ui.icon name="key" class="w-6 h-6" />
            </div>
            <h1 class="text-xl font-bold text-ink">{{ __('auth.set_new_password') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('auth.set_new_password_hint') }}</p>
        </div>

        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-ui.field :label="__('auth.current_or_temp_password')" name="current_password" required>
                <x-ui.input name="current_password" type="password" autocomplete="current-password" />
            </x-ui.field>

            <x-ui.field :label="__('auth.new_password')" name="password" :hint="__('auth.min_chars')" required>
                <x-ui.input name="password" type="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field :label="__('auth.confirm_new_password')" name="password_confirmation" required>
                <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full" size="lg">{{ __('auth.change_password') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layout.guest>
