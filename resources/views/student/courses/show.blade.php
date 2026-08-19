<x-layout.student :title="$lesson->title" :heading="$lesson->title">
    <x-ui.breadcrumbs :items="[
        'কোর্স' => route('student.courses.index'),
        $lesson->course->title => route('student.courses.index', ['course' => $lesson->course->slug]),
        $lesson->title => null,
    ]" class="mb-5" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-ui.card>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <x-ui.badge color="brand">{{ $lesson->course->title }}</x-ui.badge>
                    <span class="text-xs text-muted flex items-center gap-1"><x-ui.icon name="calendar" class="w-3.5 h-3.5" />{{ $lesson->date_label ?? '—' }}</span>
                    <span class="text-xs text-muted flex items-center gap-1"><x-ui.icon name="clock" class="w-3.5 h-3.5" />{{ $lesson->duration_label ?? '—' }}</span>
                </div>
                <h1 class="text-2xl font-black text-ink">{{ $lesson->title }}</h1>
                @if ($lesson->description)<p class="mt-2 text-muted leading-relaxed">{{ $lesson->description }}</p>@endif

                {{-- Media. Google Drive files can't be streamed into a native <audio> element
                     (Drive serves no direct, CORS-friendly media URL), so we embed Drive's own
                     player, which streams reliably and handles seeking. --}}
                @if ($lesson->embedUrl())
                    <div class="mt-5 rounded-xl overflow-hidden border border-line bg-black/5">
                        <iframe src="{{ $lesson->embedUrl() }}" class="block w-full h-24" allow="autoplay" loading="lazy" title="অডিও"></iframe>
                    </div>
                    @if ($lesson->media_url)
                        <x-ui.button :href="$lesson->media_url" target="_blank" rel="noopener" variant="secondary" class="mt-3 w-full">
                            <x-ui.icon name="download" class="w-4 h-4" /> ড্রাইভে খুলুন / ডাউনলোড
                        </x-ui.button>
                    @endif
                @endif
            </x-ui.card>

            {{-- Summary --}}
            @if (! empty($lesson->summary))
                <x-ui.card>
                    <h3 class="font-bold text-ink mb-3">সারসংক্ষেপ</h3>
                    <ul class="space-y-2">
                        @foreach ($lesson->summary as $point)
                            <li class="flex items-start gap-2 text-sm text-ink"><x-ui.icon name="check" class="w-4 h-4 text-brand mt-0.5 shrink-0" />{{ $point }}</li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-6">
            {{-- Syllabus --}}
            @if ($lesson->syllabus)
                <x-ui.card>
                    <h3 class="font-bold text-ink mb-2 flex items-center gap-2"><x-ui.icon name="book" class="w-4 h-4 text-brand" /> পরবর্তী প্রস্তুতি</h3>
                    <p class="text-sm text-muted leading-relaxed">{{ $lesson->syllabus }}</p>
                </x-ui.card>
            @endif

            {{-- Resources --}}
            <x-ui.card>
                <h3 class="font-bold text-ink mb-3">রিসোর্স ও নোট</h3>
                @forelse ($lesson->resources as $res)
                    @if ($res->url)
                        <a href="{{ $res->url }}" target="_blank" rel="noopener"
                           class="flex items-center gap-3 p-3 rounded-lg border border-line hover:border-brand/40 text-sm text-ink mb-2 transition-colors">
                            <x-ui.icon name="book" class="w-4 h-4 text-brand shrink-0" />
                            <span class="min-w-0">{!! resource_label_html($res->label) !!}</span>
                        </a>
                    @else
                        <div class="flex items-center gap-3 p-3 rounded-lg border border-dashed border-line text-sm text-muted mb-2">
                            <x-ui.icon name="book" class="w-4 h-4 shrink-0" />
                            <span class="min-w-0">{!! resource_label_html($res->label) !!}</span>
                        </div>
                    @endif
                @empty
                    <p class="text-sm text-muted italic">কোনো নোট যুক্ত করা হয়নি।</p>
                @endforelse
            </x-ui.card>
        </div>
    </div>
</x-layout.student>
