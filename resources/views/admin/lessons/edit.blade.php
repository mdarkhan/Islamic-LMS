<x-layout.admin title="ক্লাস সম্পাদনা" heading="ক্লাস সম্পাদনা">
    <x-ui.breadcrumbs :items="['ক্লাস' => route('admin.lessons.index'), $lesson->title => null]" class="mb-5" />
    <form method="POST" action="{{ route('admin.lessons.update', $lesson) }}">
        @csrf @method('PUT')
        @include('admin.lessons._form')
        <div class="flex justify-between gap-3 mt-6">
            <x-ui.button type="submit" form="lesson-delete" variant="ghost" class="text-rose-600"
                         onclick="return confirm('এই ক্লাসটি মুছে ফেলবেন?')">মুছুন</x-ui.button>
            <div class="flex gap-3">
                <x-ui.button :href="route('admin.lessons.index')" variant="ghost">বাতিল</x-ui.button>
                <x-ui.button type="submit">সংরক্ষণ</x-ui.button>
            </div>
        </div>
    </form>
    <form id="lesson-delete" method="POST" action="{{ route('admin.lessons.destroy', $lesson) }}" class="hidden">
        @csrf @method('DELETE')
    </form>
</x-layout.admin>
