<x-layout.admin title="নতুন কুইজ" heading="নতুন কুইজ">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), 'নতুন' => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.quizzes.store') }}">
        @csrf
        @include('admin.quizzes._form', ['quiz' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.quizzes.index')" variant="ghost">বাতিল</x-ui.button>
            <x-ui.button type="submit">তৈরি করে প্রশ্ন যোগ করুন</x-ui.button>
        </div>
    </form>
</x-layout.admin>
