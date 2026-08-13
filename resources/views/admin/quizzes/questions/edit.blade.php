<x-layout.admin :title="__('quizzes.edit_question')" :heading="__('quizzes.edit_question')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), $quiz->title => route('admin.quizzes.edit', $quiz), __('quizzes.edit_question') => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.quizzes.questions.update', [$quiz, $question]) }}">
        @csrf @method('PUT')
        @include('admin.quizzes.questions._form')
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.quizzes.edit', $quiz)" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ __('admin.save_changes') }}</x-ui.button>
        </div>
    </form>
</x-layout.admin>
