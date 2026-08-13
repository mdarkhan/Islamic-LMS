<x-layout.admin title="প্রশ্ন সম্পাদনা" heading="প্রশ্ন সম্পাদনা">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), $quiz->title => route('admin.quizzes.edit', $quiz), 'প্রশ্ন সম্পাদনা' => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.quizzes.questions.update', [$quiz, $question]) }}">
        @csrf @method('PUT')
        @include('admin.quizzes.questions._form')
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.quizzes.edit', $quiz)" variant="ghost">বাতিল</x-ui.button>
            <x-ui.button type="submit">সংরক্ষণ করুন</x-ui.button>
        </div>
    </form>
</x-layout.admin>
