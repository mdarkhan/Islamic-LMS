@props(['sub' => true])
<a href="{{ url('/') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5']) }}>
    <span class="grid place-items-center w-9 h-9 rounded-xl bg-brand text-brand-ink shrink-0">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 2v4M10 6h4M12 6c3.5 0 6 3 6 7H6c0-4 2.5-7 6-7ZM18 13v8M6 13v8M6 21h12M10 21v-4a2 2 0 0 1 4 0v4"/></svg>
    </span>
    <span class="leading-tight">
        <span class="block font-black text-ink">মাসউদ আলিমী</span>
        @if ($sub)<span class="block text-[11px] text-muted -mt-0.5">কুরআন তাফসির ও সীরাত কোর্স</span>@endif
    </span>
</a>
