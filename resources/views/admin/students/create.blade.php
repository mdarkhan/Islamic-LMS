<x-layout.admin :title="__('students.new_student')" :heading="__('students.new_student')">
    <x-ui.breadcrumbs :items="[__('nav.students') => route('admin.students.index'), __('ui.new') => null]" class="mb-5" />

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.students.store') }}" class="space-y-5" x-data="{ mode: '{{ old('password_mode', 'generate') }}' }">
            @csrf
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field :label="__('students.roll_number')" name="roll" required>
                    <x-ui.input name="roll" :value="old('roll')" :placeholder="__('students.roll_placeholder')" autofocus />
                </x-ui.field>
                <x-ui.field :label="__('students.name')" name="name" required>
                    <x-ui.input name="name" :value="old('name')" />
                </x-ui.field>
                <x-ui.field :label="__('students.guardian_name')" name="guardian_name" required>
                    <x-ui.input name="guardian_name" :value="old('guardian_name')" required />
                </x-ui.field>
                <x-ui.field :label="__('students.phone')" name="phone" :hint="__('ui.optional')">
                    <x-ui.input name="phone" :value="old('phone')" inputmode="tel" />
                </x-ui.field>
                <x-ui.field :label="__('students.email_optional')" name="email" class="sm:col-span-2">
                    <x-ui.input name="email" type="email" :value="old('email')" />
                </x-ui.field>
            </div>

            <div class="border-t border-line pt-5 space-y-3">
                <p class="text-sm font-semibold text-ink">{{ __('students.password_heading') }}</p>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="password_mode" value="generate" x-model="mode" class="text-brand focus:ring-brand">
                    {{ __('students.password_generate') }}
                </label>
                <label class="flex items-center gap-2 text-sm">
                    <input type="radio" name="password_mode" value="manual" x-model="mode" class="text-brand focus:ring-brand">
                    {{ __('students.password_manual') }}
                </label>
                <div x-show="mode === 'manual'" x-cloak>
                    <x-ui.field name="password">
                        <x-ui.input name="password" type="text" :placeholder="__('students.password_min_placeholder')" />
                    </x-ui.field>
                </div>
            </div>

            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.students.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('students.create_student') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
