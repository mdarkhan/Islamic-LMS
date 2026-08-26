<x-layout.student :title="__('messages.heading')" :heading="__('messages.heading')">
    {{-- The thread carries its own empty state, so there is no separate empty card to
         go stale the moment the first message is appended client-side. --}}
    <x-messages.thread
        :messages="$messages"
        :poll-url="route('student.messages.poll')"
        :send-url="route('student.messages.store')" />
</x-layout.student>
