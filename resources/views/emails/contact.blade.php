<x-mail::message>
# যোগাযোগ ফর্ম — নতুন বার্তা

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

**বার্তা:**

{{ $message }}

---

<x-mail::subcopy>
এই বার্তাটি ওয়েবসাইটের "যোগাযোগ" ফর্ম থেকে পাঠানো হয়েছে। উত্তর দিতে সরাসরি Reply করুন — এটি প্রেরকের ইমেইলে পৌঁছাবে।
</x-mail::subcopy>
</x-mail::message>
