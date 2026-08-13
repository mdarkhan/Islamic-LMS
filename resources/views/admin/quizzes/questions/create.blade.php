<x-layout.admin :title="__('quizzes.new_question')" :heading="__('quizzes.new_question')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), $quiz->title => route('admin.quizzes.edit', $quiz), __('quizzes.new_question') => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.quizzes.questions.store', $quiz) }}">
        @csrf
        @include('admin.quizzes.questions._form', ['question' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.quizzes.edit', $quiz)" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ __('quizzes.add_question_submit') }}</x-ui.button>
        </div>
    </form>
</x-layout.admin>
