<x-layout.admin :title="__('quizzes.new_quiz')" :heading="__('quizzes.new_quiz')">
    <x-ui.breadcrumbs :items="[__('nav.quizzes') => route('admin.quizzes.index'), __('ui.new') => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.quizzes.store') }}">
        @csrf
        @include('admin.quizzes._form', ['quiz' => null])
        <div class="flex justify-end gap-3 mt-6">
            <x-ui.button :href="route('admin.quizzes.index')" variant="ghost">{{ __('ui.cancel') }}</x-ui.button>
            <x-ui.button type="submit">{{ __('quizzes.create_and_add_questions') }}</x-ui.button>
        </div>
    </form>
</x-layout.admin>
