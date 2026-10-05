<x-layout.guest :title="__('auth.forgot_title')">
    <x-ui.card>
        <div class="text-center mb-6">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                <x-ui.icon name="key" class="w-6 h-6" />
            </div>
            <h1 class="text-xl font-bold text-ink">{{ __('auth.forgot_title') }}</h1>
            <p class="text-sm text-muted mt-1">{{ __('auth.forgot_hint') }}</p>
        </div>

        @if (session('status'))
            <x-ui.alert class="mb-4">{{ session('status') }}</x-ui.alert>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
            @csrf
            <x-ui.field :label="__('auth.identifier')" name="identifier" required>
                <x-ui.input name="identifier" type="text" :value="old('identifier')" autofocus autocomplete="username"
                            :placeholder="__('auth.identifier_placeholder')" />
            </x-ui.field>
            <x-ui.button type="submit" class="w-full" size="lg">{{ __('auth.send_reset_link') }}</x-ui.button>
        </form>

        <p class="text-sm text-center mt-5"><a href="{{ route('login') }}" class="text-brand font-semibold hover:underline">{{ __('auth.back_to_login') }}</a></p>
    </x-ui.card>
</x-layout.guest>
