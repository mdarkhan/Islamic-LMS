<x-layout.public :title="__('contact.heading')" :description="__('contact.intro')" :canonical="route('contact.show')">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 py-8 sm:py-12">
        <header class="mb-6">
            <h1 class="text-2xl sm:text-3xl font-black text-ink">{{ __('contact.heading') }}</h1>
            <p class="text-muted mt-1">{{ __('contact.intro') }}</p>
        </header>

        @if (session('success'))
            <x-ui.alert type="success" class="mb-5">{{ session('success') }}</x-ui.alert>
        @endif
        @if (session('error'))
            <x-ui.alert type="error" class="mb-5">{{ session('error') }}</x-ui.alert>
        @endif

        <x-ui.card>
            <form method="POST" action="{{ route('contact.store') }}" class="space-y-4">
                @csrf
                {{-- Honeypot: hidden from humans, tempting to bots. Must stay empty. --}}
                <div class="absolute -left-[9999px]" aria-hidden="true">
                    <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <x-ui.field :label="__('contact.name')" name="name" :required="true">
                        <x-ui.input name="name" value="{{ old('name') }}" required />
                    </x-ui.field>
                    <x-ui.field :label="__('contact.email')" name="email" :required="true">
                        <x-ui.input type="email" name="email" value="{{ old('email') }}" required />
                    </x-ui.field>
                    <x-ui.field :label="__('contact.mobile')" name="mobile">
                        <x-ui.input name="mobile" value="{{ old('mobile') }}" />
                    </x-ui.field>
                    <x-ui.field :label="__('contact.subject')" name="subject">
                        <x-ui.input name="subject" value="{{ old('subject') }}" />
                    </x-ui.field>
                </div>

                <x-ui.field :label="__('contact.message')" name="message" :required="true">
                    <x-ui.textarea name="message" rows="6" required>{{ old('message') }}</x-ui.textarea>
                </x-ui.field>

                <x-ui.button type="submit"><x-ui.icon name="send" class="w-4 h-4" /> {{ __('contact.send') }}</x-ui.button>

                <p class="text-xs text-muted leading-relaxed pt-2 border-t border-line">{{ __('contact.privacy') }}</p>
            </form>
        </x-ui.card>
    </div>
</x-layout.public>
