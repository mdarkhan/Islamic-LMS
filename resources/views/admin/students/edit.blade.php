<x-layout.admin :title="__('students.edit_student')" :heading="__('students.edit_student')">
    <x-ui.breadcrumbs :items="[
        __('nav.students') => route('admin.students.index'),
        $student->name => route('admin.students.show', $student),
        __('ui.edit') => null,
    ]" class="mb-5" />

    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.students.update', $student) }}" class="space-y-5">
            @csrf @method('PUT')
            <div class="grid sm:grid-cols-2 gap-4">
                <x-ui.field :label="__('students.roll_number')" name="roll" required>
                    <x-ui.input name="roll" :value="old('roll', $student->roll)" />
                </x-ui.field>
                <x-ui.field :label="__('students.name')" name="name" required>
                    <x-ui.input name="name" :value="old('name', $student->name)" />
                </x-ui.field>
                <x-ui.field :label="__('students.guardian_name')" name="guardian_name">
                    <x-ui.input name="guardian_name" :value="old('guardian_name', $student->guardian_name)" />
                </x-ui.field>
                <x-ui.field :label="__('students.phone')" name="phone">
                    <x-ui.input name="phone" :value="old('phone', $student->phone)" />
                </x-ui.field>
                <x-ui.field :label="__('students.email')" name="email" class="sm:col-span-2">
                    <x-ui.input name="email" type="email" :value="old('email', $student->email)" />
                </x-ui.field>
            </div>
            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.students.show', $student)" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
                <x-ui.button type="submit">{{ __('admin.save_changes') }}</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
