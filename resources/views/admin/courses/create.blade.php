<x-layout.admin title="নতুন কোর্স" heading="নতুন কোর্স">
    <x-ui.breadcrumbs :items="['কোর্স' => route('admin.courses.index'), 'নতুন' => null]" class="mb-5" />
    <x-ui.card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.courses.store') }}" class="space-y-5">
            @csrf
            <x-ui.field label="শিরোনাম" name="title" required>
                <x-ui.input name="title" :value="old('title')" autofocus />
            </x-ui.field>
            <x-ui.field label="বিবরণ" name="description">
                <x-ui.textarea name="description">{{ old('description') }}</x-ui.textarea>
            </x-ui.field>
            <label class="flex items-center gap-2 text-sm text-ink">
                <input type="checkbox" name="is_published" value="1" @checked(old('is_published')) class="rounded border-line text-brand focus:ring-brand">
                এখনই প্রকাশ করুন
            </label>
            <div class="flex justify-end gap-3 border-t border-line pt-5">
                <x-ui.button :href="route('admin.courses.index')" variant="ghost">বাতিল</x-ui.button>
                <x-ui.button type="submit">তৈরি করুন</x-ui.button>
            </div>
        </form>
    </x-ui.card>
</x-layout.admin>
