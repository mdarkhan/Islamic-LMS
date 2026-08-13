<x-layout.admin title="নতুন প্রশ্ন" heading="নতুন প্রশ্ন">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), $quiz->title => route('admin.quizzes.edit', $quiz), 'নতুন প্রশ্ন' => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.quizzes.questions.store', $quiz) }}">
        @csrf
        @include('admin.quizzes.questions._form', ['question' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.quizzes.edit', $quiz)" variant="ghost">বাতিল</x-ui.button>
            <x-ui.button type="submit">প্রশ্ন যোগ করুন</x-ui.button>
        </div>
    </form>
</x-layout.admin>
