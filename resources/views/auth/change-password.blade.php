<x-layout.guest title="পাসওয়ার্ড পরিবর্তন">
    <x-ui.card>
        <div class="text-center mb-6">
            <div class="mx-auto w-12 h-12 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                <x-ui.icon name="key" class="w-6 h-6" />
            </div>
            <h1 class="text-xl font-bold text-ink">নতুন পাসওয়ার্ড নির্ধারণ করুন</h1>
            <p class="text-sm text-muted mt-1">এগিয়ে যাওয়ার আগে অনুগ্রহ করে একটি নতুন পাসওয়ার্ড নির্ধারণ করুন।</p>
        </div>

        <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
            @csrf
            @method('PUT')

            <x-ui.field label="বর্তমান / অস্থায়ী পাসওয়ার্ড" name="current_password" required>
                <x-ui.input name="current_password" type="password" autocomplete="current-password" />
            </x-ui.field>

            <x-ui.field label="নতুন পাসওয়ার্ড" name="password" hint="কমপক্ষে ৬ অক্ষর" required>
                <x-ui.input name="password" type="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.field label="নতুন পাসওয়ার্ড নিশ্চিত করুন" name="password_confirmation" required>
                <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
            </x-ui.field>

            <x-ui.button type="submit" class="w-full" size="lg">পাসওয়ার্ড পরিবর্তন করুন</x-ui.button>
        </form>
    </x-ui.card>
</x-layout.guest>
