<x-mail::message>
# উস্তাযকে জিজ্ঞাসা — নতুন প্রশ্ন

**নাম:** {{ $name }}
**ইমেইল:** {{ $email }}
@if ($mobile)
**মোবাইল:** {{ $mobile }}
@endif
@if ($topic)
**বিষয়:** {{ $topic }}
@endif
**সময়:** {{ $submittedAt }}

---

**প্রশ্ন:**

{{ $question }}

---

<x-mail::subcopy>
এই প্রশ্নটি ওয়েবসাইটের “উস্তাযকে জিজ্ঞাসা” ফর্ম থেকে পাঠানো হয়েছে। উত্তর দিতে সরাসরি Reply করুন — এটি প্রশ্নকারীর ইমেইলে পৌঁছাবে।
</x-mail::subcopy>
</x-mail::message>
