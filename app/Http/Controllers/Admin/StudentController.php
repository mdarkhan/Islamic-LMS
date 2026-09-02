<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StudentStoreRequest;
use App\Http\Requests\Admin\StudentUpdateRequest;
use App\Models\Conversation;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\StudentSort;
use App\Support\TemporaryPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse as BaseStreamedResponse;

class StudentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');
        [$sort, $dir] = StudentSort::resolve($request);

        $query = $this->filteredQuery($search, $status);
        StudentSort::apply($query, $sort, $dir);

        $students = $query->paginate(20)->withQueryString();

        return view('admin.students.index', compact('students', 'search', 'status', 'sort', 'dir'));
    }

    /** Filtered CSV export — the same columns shown on the list, unpaginated. */
    public function export(Request $request): BaseStreamedResponse
    {
        $search = trim((string) $request->query('q', ''));
        $status = $request->query('status');

        $query = $this->filteredQuery($search, $status)->orderBy('name');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM so Excel reads Bengali (utf8mb4) correctly
            fputcsv($out, ['Name', 'Roll', 'Guardian', 'Email', 'Phone', 'Status', 'Points']);

            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $student) {
                    fputcsv($out, [
                        $student->name, $student->roll, $student->guardian_name,
                        $student->email, $student->phone, $student->status, $student->points_balance,
                    ]);
                }
            });

            fclose($out);
        }, 'students-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<User>
     */
    private function filteredQuery(string $search, ?string $status)
    {
        return User::query()->students()
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('name', 'like', "%{$search}%")
                ->orWhere('roll', 'like', "%{$search}%")))
            ->when(in_array($status, ['active', 'suspended', 'archived'], true), fn ($q) => $q->where('status', $status));
    }

    public function create(): View
    {
        return view('admin.students.create');
    }

    public function store(StudentStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $generated = null;

        if ($data['password_mode'] === 'generate') {
            $generated = TemporaryPassword::generate();
            $plain = $generated;
        } else {
            $plain = $data['password'];
        }

        $student = DB::transaction(function () use ($data, $plain, $generated) {
            $student = User::query()->create([
                'roll' => $data['roll'],
                'name' => $data['name'],
                'guardian_name' => $data['guardian_name'] ?? null,
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($plain),   // hashed immediately; plaintext never stored
                'status' => User::STATUS_ACTIVE,
                'force_password_change' => $generated !== null,
            ]);
            $student->assignRole(Role::STUDENT);

            return $student;
        });

        $this->audit->log('student.created', $student, after: [
            'roll' => $student->roll, 'name' => $student->name, 'status' => $student->status,
        ]);

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'শিক্ষার্থী তৈরি করা হয়েছে।')
            ->with($generated ? 'temp_password' : '_', $generated);
    }

    public function show(User $student): View
    {
        $this->ensureStudent($student);

        $student->load(['pointTransactions' => fn ($q) => $q->with('performedBy')->latest('id')->limit(10)]);

        $attempts = $student->quizAttempts()
            ->with('quiz')
            ->where('kind', 'official')
            ->latest('id')
            ->limit(10)
            ->get();

        // Existing thread only — visiting a profile must never silently create one
        // (MessageService::threadFor is reserved for an admin who actually writes in).
        $conversation = Conversation::query()->where('user_id', $student->id)->first();

        return view('admin.students.show', compact('student', 'attempts', 'conversation'));
    }

    public function edit(User $student): View
    {
        $this->ensureStudent($student);

        return view('admin.students.edit', compact('student'));
    }

    public function update(StudentUpdateRequest $request, User $student): RedirectResponse
    {
        $this->ensureStudent($student);

        $before = $student->only(['roll', 'name', 'guardian_name', 'email', 'phone']);
        $student->fill($request->validated())->save();

        $this->audit->log('student.updated', $student,
            before: $before,
            after: $student->only(['roll', 'name', 'guardian_name', 'email', 'phone']),
        );

        return redirect()->route('admin.students.show', $student)
            ->with('success', 'শিক্ষার্থীর তথ্য হালনাগাদ করা হয়েছে।');
    }

    /**
     * Suspend / reactivate / archive. Suspending or archiving also drops the
     * student's active sessions so a blocked account cannot keep browsing.
     */
    public function updateStatus(Request $request, User $student): RedirectResponse
    {
        $this->ensureStudent($student);

        $data = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED, User::STATUS_ARCHIVED])],
        ]);

        if ($student->id === $request->user()->id) {
            throw ValidationException::withMessages(['status' => 'আপনি নিজের অ্যাকাউন্টের স্ট্যাটাস পরিবর্তন করতে পারবেন না।']);
        }

        $before = $student->status;
        $student->forceFill(['status' => $data['status']])->save();

        if ($data['status'] !== User::STATUS_ACTIVE) {
            DB::table('sessions')->where('user_id', $student->id)->delete();
        }

        $this->audit->log('student.status_changed', $student,
            before: ['status' => $before],
            after: ['status' => $student->status],
        );

        return back()->with('success', 'শিক্ষার্থীর স্ট্যাটাস পরিবর্তন করা হয়েছে।');
    }

    /**
     * Bulk suspend / reactivate / archive. The same session-kill rule as the single-
     * student path applies; the acting admin is silently excluded from the selection
     * (never suspend yourself via a bulk sweep) rather than rejecting the whole batch.
     */
    public function bulkStatus(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer'],
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED, User::STATUS_ARCHIVED])],
        ]);

        $students = User::query()->students()
            ->whereIn('id', $data['student_ids'])
            ->where('id', '!=', $request->user()->id)
            ->get();

        DB::transaction(function () use ($students, $data) {
            foreach ($students as $student) {
                $student->forceFill(['status' => $data['status']])->save();

                if ($data['status'] !== User::STATUS_ACTIVE) {
                    DB::table('sessions')->where('user_id', $student->id)->delete();
                }
            }
        });

        $this->audit->log('student.bulk_status_changed', after: [
            'students' => $students->count(), 'status' => $data['status'],
        ]);

        return back()->with('success', $students->count().' জন শিক্ষার্থীর স্ট্যাটাস পরিবর্তন করা হয়েছে।');
    }

    /**
     * Issue a temporary password, force a change at next login, and end existing
     * sessions. The plaintext is shown once for delivery and never persisted.
     */
    public function resetPassword(Request $request, User $student): RedirectResponse
    {
        $this->ensureStudent($student);

        $temp = TemporaryPassword::generate();

        $student->forceFill([
            'password' => Hash::make($temp),
            'force_password_change' => true,
        ])->save();

        DB::table('sessions')->where('user_id', $student->id)->delete();

        $this->audit->log('student.password_reset', $student);

        return back()
            ->with('success', 'নতুন অস্থায়ী পাসওয়ার্ড তৈরি করা হয়েছে। এটি শিক্ষার্থীকে জানিয়ে দিন।')
            ->with('temp_password', $temp);
    }

    private function ensureStudent(User $student): void
    {
        abort_unless($student->isStudent(), 404);
    }
}
