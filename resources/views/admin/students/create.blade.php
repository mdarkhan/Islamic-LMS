<x-layout.admin title="নতুন শিক্ষার্থী" heading="নতুন শিক্ষার্থী">
    <x-ui.breadcrumbs :items="['শিক্ষার্থী' => route('admin.students.index'), 'নতুন' => null]" class="mb-5" />

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.students.store') }}" class="space-y-5" x-data="{ mode: '{{ old('password_mode', 'generate') }}' }">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field label="রোল নম্বর" name="roll" required>
                    <x-ui.input name="roll" :value="old('roll')" placeholder="যেমনঃ ১০১" autofocus />
                </x-ui.field>
                <x-ui.field label="নাম" name="name" required>
                    <x-ui.input name="name" :value="old('name')" />
                </x-ui.field>
                <x-ui.field label="পিতা/স্বামীর নাম" name="guardian_name">
                    <x-ui.input name="guardian_name" :value="old('guardian_name')" />
                </x-ui.field>
                <x-ui.field label="মোবাইল" name="phone">
                    <x-ui.input name="phone" :value="old('phone')" />
                </x-ui.field>
                <x-ui.field label="ইমেইল (ঐচ্ছিক)" name="email" class="sm:col-span-2">
                    <x-ui.input name="email" type="email" :value="old('email')" />
                </x-ui.field>
            </div>

            <div class="border-t border-line pt-5 space-y-3">
                <p class="text-sm font-semibold text-ink">পাসওয়ার্ড</p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="password_mode" value="generate" x-model="mode" class="text-brand focus:ring-brand">
                    স্বয়ংক্রিয় অস্থায়ী পাসওয়ার্ড তৈরি করুন (প্রথম লগইনে পরিবর্তন বাধ্যতামূলক)
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="password_mode" value="manual" x-model="mode" class="text-brand focus:ring-brand">
                    নিজে পাসওয়ার্ড নির্ধারণ করুন
                </label>
                <div x-show="mode === 'manual'" x-cloak>
                    <x-ui.field name="password">
                        <x-ui.input name="password" type="text" placeholder="কমপক্ষে ৬ অক্ষর" />
                    </x-ui.field>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.students.index')" variant="ghost">বাতিল</x-ui.button>
                <x-ui.button type="submit">শিক্ষার্থী তৈরি করুন</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
