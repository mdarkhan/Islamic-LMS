<x-layout.admin :title="__('messages.thread_heading')" :heading="$conversation->student->name">
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div class="flex items-center gap-2 min-w-0">
            <x-ui.button :href="route('admin.messages.index')" variant="ghost" size="sm">
                {{ __('messages.back_to_inbox') }}
            </x-ui.button>
            @if ($conversation->student->roll)
                <span class="text-sm text-muted tabular-nums">{{ bn($conversation->student->roll) }}</span>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.messages.destroy', $conversation) }}"
              data-confirm="{{ __('messages.confirm_delete') }}">
            @csrf @method('DELETE')
            <button class="inline-flex items-center gap-1.5 text-sm text-muted hover:text-rose-600 transition-colors">
                <x-ui.icon name="close" class="w-4 h-4" /> {{ __('messages.delete_thread') }}
            </button>
        </form>
    </div>

    <x-messages.thread
        :messages="$messages"
        :poll-url="route('admin.messages.poll', $conversation)"
        :send-url="route('admin.messages.store', $conversation)" />
</x-layout.admin>
