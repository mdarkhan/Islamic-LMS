<x-mail::message>
# {{ $headline }}

আসসালামু আলাইকুম {{ $recipientName }},

{{ $line }}

<x-mail::button :url="$url">
{{ $buttonLabel }}
</x-mail::button>

<x-mail::subcopy>
এই ইমেইল আপনার প্রোফাইলে দেওয়া ঠিকানায় পাঠানো হয়েছে। ইমেইল বন্ধ করতে সাইটে লগইন করে প্রোফাইল থেকে "ইমেইল নোটিফিকেশন" বন্ধ করুন।
</x-mail::subcopy>
</x-mail::message>
