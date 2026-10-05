<x-layout.guest :title="__('auth.set_new_password')">
    <x-ui.card>
        <div class="text-center mb-6">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                <x-ui.icon name="key" class="w-6 h-6" />
            </div>
            <h1 class="text-xl font-bold text-ink">{{ __('auth.set_new_password') }}</h1>
        </div>

        <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <x-ui.field :label="__('auth.email')" name="email" required>
                <x-ui.input name="email" type="email" :value="old('email', $email)" autocomplete="username" readonly />
            </x-ui.field>
            <x-ui.field :label="__('auth.new_password')" name="password" :hint="__('auth.min_chars_reset')" required>
                <x-ui.input name="password" type="password" autocomplete="new-password" autofocus />
            </x-ui.field>
            <x-ui.field :label="__('auth.confirm_new_password')" name="password_confirmation" required>
                <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full" size="lg">{{ __('auth.change_password') }}</x-ui.button>
        </form>
    </x-ui.card>
</x-layout.guest>
