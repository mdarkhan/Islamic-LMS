<x-layout.admin :title="__('admin.account_heading')" :heading="__('admin.account_heading')">
    <div class="max-w-xl space-y-6">
        <x-flash />

        <x-ui.card>
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 rounded-2xl bg-brand-tint text-brand grid place-items-center shrink-0">
                    <x-ui.icon name="profile" class="w-7 h-7" />
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-ink text-lg truncate">{{ $user->name }}</p>
                    <p class="text-sm text-muted truncate">{{ $user->email }}</p>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-4">{{ __('profile.change_password') }}</h3>
            <form method="POST" action="{{ route('admin.account.password') }}" class="space-y-4">
                @csrf @method('PUT')
                <x-ui.field :label="__('profile.current_password')" name="current_password" required>
                    <x-ui.input name="current_password" type="password" autocomplete="current-password" />
                </x-ui.field>
                <x-ui.field :label="__('profile.new_password')" name="password" :hint="__('auth.min_chars_admin')" required>
                    <x-ui.input name="password" type="password" autocomplete="new-password" />
                </x-ui.field>
                <x-ui.field :label="__('profile.confirm')" name="password_confirmation" required>
                    <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
                </x-ui.field>
                <div class="flex justify-end">
                    <x-ui.button type="submit">{{ __('profile.change_password') }}</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    </div>
</x-layout.admin>
