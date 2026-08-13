<x-layout.guest :title="__('auth.enter')">
    <x-ui.card>
        <div class="text-center mb-6">
            <p class="font-arabic text-xl text-brand mb-3">بِسْمِ ٱللَّٰهِ ٱلرَّحْمَٰنِ ٱلرَّحِيمِ</p>
            <h1 class="text-xl font-bold text-ink">{{ __('auth.sign_in') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('auth.sign_in_hint') }}</p>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
            @csrf

            <x-ui.field :label="__('auth.identifier')" name="identifier" required>
                <x-ui.input name="identifier" type="text" :value="old('identifier')"
                            autofocus autocomplete="username" inputmode="text"
                            :placeholder="__('auth.identifier_placeholder')" />
            </x-ui.field>

            <x-ui.field :label="__('auth.password')" name="password" required>
                <div x-data="{ show: false }" class="relative">
                    <x-ui.input name="password" x-bind:type="show ? 'text' : 'password'"
                                autocomplete="current-password" :placeholder="__('auth.password_placeholder')" class="pr-11" />
                    <button type="button" @click="show = !show" tabindex="-1"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-muted hover:text-ink" aria-label="{{ __('auth.show_password') }}">
                        <x-ui.icon name="profile" class="w-5 h-5" x-show="!show" />
                        <x-ui.icon name="close" class="w-5 h-5" x-show="show" x-cloak />
                    </button>
                </div>
            </x-ui.field>

            <label class="flex items-center gap-2 text-sm text-muted select-none">
                <input type="checkbox" name="remember" class="rounded border-line text-brand focus:ring-brand">
                {{ __('auth.remember') }}
            </label>

            <x-ui.button type="submit" class="w-full" size="lg">{{ __('auth.enter') }}</x-ui.button>
        </form>

        <p class="text-xs text-muted text-center mt-6">
            {{ __('auth.help') }}
        </p>
    </x-ui.card>
</x-layout.guest>
