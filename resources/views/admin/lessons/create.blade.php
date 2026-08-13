<x-layout.admin title="নতুন ক্লাস" heading="নতুন ক্লাস">
    <x-ui.breadcrumbs :items="['ক্লাস' => route('admin.lessons.index'), 'নতুন' => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.lessons.store') }}">
        @csrf
        @include('admin.lessons._form', ['lesson' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.lessons.index')" variant="ghost">বাতিল</x-ui.button>
            <x-ui.button type="submit">তৈরি করুন</x-ui.button>
        </div>
    </form>
</x-layout.admin>
