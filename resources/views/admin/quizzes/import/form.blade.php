<x-layout.admin title="কুইজ ইমপোর্ট" heading="কুইজ ইমপোর্ট">
    <x-ui.breadcrumbs :items="['কুইজ' => route('admin.quizzes.index'), 'ইমপোর্ট' => null]" class="mb-5" />

    @isset($parseError)
        <x-ui.alert type="error" title="ফাইল পড়া যায়নি" class="mb-6">{{ $parseError }}</x-ui.alert>
    @endisset

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2">
            <form method="POST" action="{{ route('admin.quizzes.import.upload') }}" enctype="multipart/form-data" class="space-y-5">
                @csrf
                <x-ui.field label="CSV অথবা XLSX ফাইল" name="file" hint="সর্বোচ্চ ৫ MB। XLSX হলে ওয়ার্কশিট নির্বাচন করা যাবে।" required>
                    <input type="file" name="file" accept=".csv,.xlsx,text/csv"
                           class="block w-full text-sm text-muted file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-brand file:text-brand-ink file:font-semibold hover:file:bg-brand-strong file:cursor-pointer">
                </x-ui.field>
                <x-ui.button type="submit"><x-ui.icon name="import" class="w-4 h-4" /> আপলোড ও প্রিভিউ</x-ui.button>
            </form>
        </x-ui.card>

        <x-ui.card>
            <h3 class="font-bold text-ink mb-3">সহজ পদ্ধতি</h3>
            <ol class="text-sm text-muted space-y-2 list-decimal list-inside">
                <li>Google Sheet খুলুন</li>
                <li>File → Download → <span class="font-medium text-ink">Microsoft Excel (.xlsx)</span></li>
                <li>এখানে আপলোড করুন ও ওয়ার্কশিট বাছুন</li>
                <li>প্রিভিউ দেখে নিশ্চিত করুন</li>
            </ol>
            <p class="text-xs text-muted mt-4 border-t border-line pt-3 leading-relaxed">
                পুরনো ফরম্যাট সমর্থিত: প্রশ্ন, ১–১২টি অপশন, সঠিক উত্তর (১ / ১,২), টাইমার, শুরু/শেষ সময়, Exam Name ও Mega Question (কাস্টম নম্বর)। পুরনো কুইজ পাসওয়ার্ড কলাম উপেক্ষা করা হয়। Google Sheet রানটাইমে ব্যবহৃত হয় না।
            </p>
        </x-ui.card>
    </div>
</x-layout.admin>
