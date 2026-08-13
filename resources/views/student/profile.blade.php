<x-layout.student title="প্রোফাইল" heading="প্রোফাইল">
    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Identity (admin-authoritative) --}}
        <x-ui.card class="lg:col-span-1">
            <div class="text-center">
                <div class="mx-auto w-16 h-16 rounded-2xl bg-brand-tint text-brand grid place-items-center mb-3">
                    <x-ui.icon name="profile" class="w-8 h-8" />
                </div>
                <h2 class="font-bold text-ink text-lg">{{ $user->name }}</h2>
                <p class="text-sm text-muted">রোল: {{ bn($user->roll) }}</p>
            </div>
            <dl class="mt-6 space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-muted">অভিভাবক</dt><dd class="text-ink font-medium text-right">{{ $user->guardian_name ?? '—' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-muted">স্ট্যাটাস</dt><dd><x-ui.badge color="success">সক্রিয়</x-ui.badge></dd></div>
            </dl>
            <p class="mt-4 text-xs text-muted border-t border-line pt-3">
                নাম, রোল ও অভিভাবকের তথ্য পরিবর্তনের জন্য কর্তৃপক্ষের সাথে যোগাযোগ করুন।
            </p>
        </x-ui.card>

        <div class="lg:col-span-2 space-y-6">
            {{-- Contact details --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-4">যোগাযোগের তথ্য</h3>
                <form method="POST" action="{{ route('student.profile.update') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.field label="ইমেইল" name="email">
                        <x-ui.input name="email" type="email" :value="old('email', $user->email)" placeholder="email@example.com" />
                    </x-ui.field>
                    <x-ui.field label="মোবাইল নম্বর" name="phone">
                        <x-ui.input name="phone" type="text" :value="old('phone', $user->phone)" placeholder="01XXXXXXXXX" />
                    </x-ui.field>
                    <div class="flex justify-end"><x-ui.button type="submit">সংরক্ষণ করুন</x-ui.button></div>
                </form>
            </x-ui.card>

            {{-- Password --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-4">পাসওয়ার্ড পরিবর্তন</h3>
                <form method="POST" action="{{ route('student.profile.password') }}" class="space-y-4">
                    @csrf @method('PUT')
                    <x-ui.field label="বর্তমান পাসওয়ার্ড" name="current_password" required>
                        <x-ui.input name="current_password" type="password" autocomplete="current-password" />
                    </x-ui.field>
                    <div class="grid sm:grid-cols-2 gap-4">
                        <x-ui.field label="নতুন পাসওয়ার্ড" name="password" hint="কমপক্ষে ৬ অক্ষর" required>
                            <x-ui.input name="password" type="password" autocomplete="new-password" />
                        </x-ui.field>
                        <x-ui.field label="নিশ্চিত করুন" name="password_confirmation" required>
                            <x-ui.input name="password_confirmation" type="password" autocomplete="new-password" />
                        </x-ui.field>
                    </div>
                    <div class="flex justify-end"><x-ui.button type="submit">পাসওয়ার্ড পরিবর্তন করুন</x-ui.button></div>
                </form>
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
