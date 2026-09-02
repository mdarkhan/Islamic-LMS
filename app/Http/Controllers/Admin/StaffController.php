<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StaffStoreRequest;
use App\Http\Requests\Admin\StaffUpdateRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\TemporaryPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin/staff account management — deliberately super_admin-only (route middleware),
 * not delegable via `perm:`: creating another admin, or reassigning who holds which
 * role, is a super-user capability that a regular admin must never be able to grant
 * itself by having that permission handed to it.
 */
class StaffController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $staff = User::query()->staff()
            ->with('roles')
            ->orderBy('name')
            ->paginate(20);

        return view('admin.staff.index', compact('staff'));
    }

    public function create(): View
    {
        return view('admin.staff.create', [
            'roles' => Role::query()->where('name', '!=', Role::STUDENT)->orderBy('label')->get(),
        ]);
    }

    public function store(StaffStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $temp = TemporaryPassword::generate();

        $staff = DB::transaction(function () use ($data, $temp) {
            $staff = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($temp),
                'status' => User::STATUS_ACTIVE,
                'force_password_change' => true,
            ]);

            $role = Role::query()->findOrFail($data['role_id']);
            $staff->assignRole($role->name);

            return $staff;
        });

        $this->audit->log('staff.created', $staff, after: [
            'name' => $staff->name, 'email' => $staff->email, 'role' => $staff->roles->pluck('name')->all(),
        ]);

        return redirect()->route('admin.staff.index')
            ->with('success', 'স্টাফ অ্যাকাউন্ট তৈরি করা হয়েছে।')
            ->with('temp_password', $temp);
    }

    public function edit(User $staff): View
    {
        $this->ensureStaff($staff);

        return view('admin.staff.edit', [
            'staff' => $staff,
            'roles' => Role::query()->where('name', '!=', Role::STUDENT)->orderBy('label')->get(),
            'currentRoleId' => $staff->roles->first()?->id,
        ]);
    }

    public function update(StaffUpdateRequest $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);

        $data = $request->validated();
        $isSelf = $staff->id === $request->user()->id;
        $currentRoleId = $staff->roles->first()?->id;

        if ($isSelf && (int) $data['role_id'] !== $currentRoleId) {
            throw ValidationException::withMessages(['role_id' => 'আপনি নিজের রোল পরিবর্তন করতে পারবেন না।']);
        }

        $newRole = Role::query()->findOrFail($data['role_id']);

        $before = $staff->only(['name', 'email']);

        DB::transaction(function () use ($staff, $data, $newRole) {
            $staff->fill(['name' => $data['name'], 'email' => $data['email']])->save();
            $staff->roles()->sync([$newRole->id]);
            $staff->unsetRelation('roles');
        });

        $this->audit->log('staff.updated', $staff,
            before: $before,
            after: ['name' => $staff->name, 'email' => $staff->email, 'role' => $newRole->name],
        );

        return redirect()->route('admin.staff.index')->with('success', 'স্টাফের তথ্য হালনাগাদ করা হয়েছে।');
    }

    public function updateStatus(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);

        $data = $request->validate([
            'status' => ['required', Rule::in([User::STATUS_ACTIVE, User::STATUS_SUSPENDED, User::STATUS_ARCHIVED])],
        ]);

        if ($staff->id === $request->user()->id) {
            throw ValidationException::withMessages(['status' => 'আপনি নিজের অ্যাকাউন্টের স্ট্যাটাস পরিবর্তন করতে পারবেন না।']);
        }

        $before = $staff->status;
        $staff->forceFill(['status' => $data['status']])->save();

        if ($data['status'] !== User::STATUS_ACTIVE) {
            DB::table('sessions')->where('user_id', $staff->id)->delete();
        }

        $this->audit->log('staff.status_changed', $staff,
            before: ['status' => $before],
            after: ['status' => $staff->status],
        );

        return back()->with('success', 'স্টাফের স্ট্যাটাস পরিবর্তন করা হয়েছে।');
    }

    public function resetPassword(Request $request, User $staff): RedirectResponse
    {
        $this->ensureStaff($staff);

        $temp = TemporaryPassword::generate();

        $staff->forceFill([
            'password' => Hash::make($temp),
            'force_password_change' => true,
        ])->save();

        DB::table('sessions')->where('user_id', $staff->id)->delete();

        $this->audit->log('staff.password_reset', $staff);

        return back()
            ->with('success', 'নতুন অস্থায়ী পাসওয়ার্ড তৈরি করা হয়েছে। এটি স্টাফকে জানিয়ে দিন।')
            ->with('temp_password', $temp);
    }

    private function ensureStaff(User $staff): void
    {
        abort_if($staff->isStudent(), 404);
    }
}
