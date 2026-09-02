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

                {{-- Video (YouTube) --}}
                @if ($lesson->youtubeEmbedUrl())
                    <div class="mt-5 rounded-2xl overflow-hidden border border-line shadow-sm bg-black">
                        <div class="relative w-full" style="padding-bottom:56.25%">
                            <iframe
                                src="{{ $lesson->youtubeEmbedUrl() }}?rel=0&modestbranding=1&playsinline=1"
                                class="absolute inset-0 w-full h-full"
                                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share; fullscreen"
                                allowfullscreen
                                loading="lazy"
                                title="{{ $lesson->title }}"
                            ></iframe>
                        </div>
                    </div>
                @endif

                {{-- Audio (Google Drive / external). Google Drive files can't be streamed
                     into a native <audio> element (Drive serves no direct, CORS-friendly
                     media URL), so we embed Drive's own player. --}}
                @if ($lesson->embedUrl())
                    <div class="{{ $lesson->youtubeEmbedUrl() ? 'mt-4' : 'mt-5' }}">
                        <div class="flex items-center gap-2 mb-2">
                            <x-ui.icon name="volume" class="w-4 h-4 text-brand" />
                            <span class="text-sm font-semibold text-ink">{{ __('lessons.audio') }}</span>
                        </div>
                        <div class="rounded-xl overflow-hidden border border-line bg-surface-raised">
                            <iframe src="{{ $lesson->embedUrl() }}" class="block w-full h-24" allow="autoplay" loading="lazy" title="{{ __('lessons.audio') }}"></iframe>
                        </div>
                    </div>
                    @if ($lesson->media_url)
                        <x-ui.button :href="$lesson->media_url" target="_blank" rel="noopener" variant="secondary" class="mt-3 w-full">
                            <x-ui.icon name="download" class="w-4 h-4" /> {{ __('lessons.open_in_drive') }}
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

    {{-- Previous / next class navigation --}}
    @if ($previousLesson || $nextLesson)
        <div class="mt-6 flex items-center gap-3">
            @if ($previousLesson)
                <x-ui.button :href="route('student.courses.show', $previousLesson->slug)" variant="secondary" class="flex-1 justify-start">
                    <x-ui.icon name="chevron" class="w-4 h-4 rotate-180" />
                    <span class="min-w-0 text-left">
                        <span class="block text-xs text-muted font-normal">{{ __('lessons.previous_lesson') }}</span>
                        <span class="block truncate">{{ $previousLesson->title }}</span>
                    </span>
                </x-ui.button>
            @endif
            @if ($nextLesson)
                <x-ui.button :href="route('student.courses.show', $nextLesson->slug)" class="flex-1 justify-end">
                    <span class="min-w-0 text-right">
                        <span class="block text-xs text-brand-ink/70 font-normal">{{ __('lessons.next_lesson') }}</span>
                        <span class="block truncate">{{ $nextLesson->title }}</span>
                    </span>
                    <x-ui.icon name="chevron" class="w-4 h-4" />
                </x-ui.button>
            @endif
        </div>
    @endif
</x-layout.student>
