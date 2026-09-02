<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RoleRequest;
use App\Models\Permission;
use App\Models\Role;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Role & permission management — super_admin-only (route middleware). The `super_admin`
 * and `student` roles are locked from permission editing here (see Role::isProtected):
 * the former bypasses permission checks entirely, the latter's grants are never consulted,
 * so editing either would be a functionally-inert, confusing control.
 */
class RoleController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        return view('admin.roles.index', [
            'roles' => Role::query()->withCount(['permissions', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.roles.create', [
            'permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ]);
    }

    public function store(RoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::query()->create(['name' => $data['name'], 'label' => $data['label']]);
        $role->permissions()->sync($data['permissions'] ?? []);

        $this->audit->log('role.created', $role, after: [
            'name' => $role->name, 'label' => $role->label, 'permissions' => count($data['permissions'] ?? []),
        ]);

        return redirect()->route('admin.roles.index')->with('success', 'নতুন রোল তৈরি করা হয়েছে।');
    }

    public function edit(Role $role): View
    {
        abort_if($role->isProtected(), 403, 'এই রোল সম্পাদনাযোগ্য নয়।');

        $role->load('permissions:id');

        return view('admin.roles.edit', [
            'role' => $role,
            'permissionGroups' => Permission::query()->orderBy('group')->orderBy('name')->get()->groupBy('group'),
            'grantedIds' => $role->permissions->pluck('id')->all(),
        ]);
    }

    public function update(RoleRequest $request, Role $role): RedirectResponse
    {
        abort_if($role->isProtected(), 403, 'এই রোল সম্পাদনাযোগ্য নয়।');

        $data = $request->validated();
        $before = ['label' => $role->label, 'permissions' => $role->permissions()->count()];

        $role->fill(['label' => $data['label']])->save();
        $role->permissions()->sync($data['permissions'] ?? []);

        $this->audit->log('role.updated', $role,
            before: $before,
            after: ['label' => $role->label, 'permissions' => count($data['permissions'] ?? [])],
        );

        return redirect()->route('admin.roles.index')->with('success', 'রোল হালনাগাদ করা হয়েছে।');
    }

    public function destroy(Role $role): RedirectResponse
    {
        abort_if($role->isBuiltIn(), 403, 'এই রোল মুছে ফেলা যাবে না।');

        if ($role->users()->exists()) {
            throw ValidationException::withMessages(['role' => 'এই রোলে এখনো ব্যবহারকারী আছে — প্রথমে তাদের অন্য রোলে সরান।']);
        }

        $this->audit->log('role.deleted', null, before: ['name' => $role->name, 'label' => $role->label]);

        $role->delete();

        return redirect()->route('admin.roles.index')->with('success', 'রোল মুছে ফেলা হয়েছে।');
    }
}
