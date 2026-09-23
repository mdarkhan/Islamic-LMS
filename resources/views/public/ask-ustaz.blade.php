<x-layout.public :title="__('ask_ustaz.heading')" :description="__('ask_ustaz.intro')" :canonical="route('ask-ustaz.show')">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-black text-ink">{{ __('ask_ustaz.heading') }}</h1>
            <p class="text-muted mt-1">{{ __('ask_ustaz.intro') }}</p>
        </header>

        @if (session('success'))
            <x-ui.alert type="success" class="mb-5">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert type="error" class="mb-5">{{ session('error') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ route('ask-ustaz.store') }}" class="space-y-4">
                @csrf
                {{-- Honeypot: hidden from humans, tempting to bots. Must stay empty. --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-ui.field :label="__('ask_ustaz.name')" name="name" :required="true">
                        <x-ui.input name="name" value="{{ old('name') }}" required />
                    </x-ui.field>
                    <x-ui.field :label="__('ask_ustaz.email')" name="email" :required="true">
                        <x-ui.input type="email" name="email" value="{{ old('email') }}" required />
                    </x-ui.field>
                    <x-ui.field :label="__('ask_ustaz.mobile')" name="mobile">
                        <x-ui.input name="mobile" value="{{ old('mobile') }}" />
                    </x-ui.field>
                    <x-ui.field :label="__('ask_ustaz.subject')" name="subject">
                        <x-ui.input name="subject" value="{{ old('subject') }}" />
                    </x-ui.field>
                </div>

                <x-ui.field :label="__('ask_ustaz.question')" name="question" :required="true">
                    <x-ui.textarea name="question" rows="6" required>{{ old('question') }}</x-ui.textarea>
                </x-ui.field>

                <x-ui.button type="submit"><x-ui.icon name="profile" class="w-4 h-4" /> {{ __('ask_ustaz.send') }}</x-ui.button>

                <p class="text-xs text-muted leading-relaxed pt-2 border-t border-line">{{ __('ask_ustaz.privacy') }}</p>
            </form>
        </x-ui.card>
    </div>
</x-layout.public>
