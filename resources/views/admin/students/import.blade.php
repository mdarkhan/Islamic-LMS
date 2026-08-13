<x-layout.admin title="শিক্ষার্থী ইমপোর্ট" heading="শিক্ষার্থী ইমপোর্ট">
    <x-ui.breadcrumbs :items="['শিক্ষার্থী' => route('admin.students.index'), 'ইমপোর্ট' => null]" class="mb-5" />

    @isset($parseError)
        <x-ui.alert type="error" title="ফাইল পড়া যায়নি" class="mb-6">{{ $parseError }}</x-ui.alert>
    @endisset

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.students.import.preview') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <x-ui.field label="CSV অথবা XLSX ফাইল" name="file" hint="সর্বোচ্চ ৫ MB। প্রথম শিটটি পড়া হবে।" required>
                    <input type="file" name="file" accept=".csv,.xlsx,text/csv"
                           class="block w-full text-sm text-muted file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-brand file:text-brand-ink file:font-semibold hover:file:bg-brand-strong file:cursor-pointer">
                </x-ui.field>
                <x-ui.button type="submit"><x-ui.icon name="import" class="w-4 h-4" /> প্রিভিউ দেখুন</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-3">কলাম</h3>
            <ul class="text-sm text-muted space-y-2">
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> Roll No. (আবশ্যক)</li>
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> Name (আবশ্যক)</li>
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> Father's/Husband's Name</li>
                <li class="flex items-center gap-2"><x-ui.icon name="check" class="w-4 h-4 text-brand" /> Password</li>
            </ul>
            <p class="text-xs text-muted mt-4 border-t border-line pt-3 leading-relaxed">
                পাসওয়ার্ড সঙ্গে সঙ্গে হ্যাশ করা হয়। প্রতিটি ইমপোর্ট করা অ্যাকাউন্টের প্রথম লগইনে পাসওয়ার্ড পরিবর্তন বাধ্যতামূলক। বিদ্যমান রোল বাদ দেওয়া হবে।
            </p>
        </x-ui.card>
    </div>
</x-layout.admin>
