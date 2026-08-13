@props(['head' => null])
<div class="bg-card border border-line rounded-[--radius-card] shadow-[--shadow-soft] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            @if ($head)
                <thead class="bg-ink/[0.03] border-b border-line text-xs uppercase tracking-wider text-muted">
                    <tr>{{ $head }}</tr>
                </thead>
            @endif
            <tbody class="divide-y divide-line">{{ $slot }}</tbody>
        </table>
    </div>
</div>
