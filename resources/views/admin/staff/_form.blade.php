@php $isEdit = isset($staff); @endphp
<div class="grid sm:grid-cols-2 gap-4">
    <x-ui.field :label="__('staff.name')" name="name" required>
        <x-ui.input name="name" :value="old('name', $staff->name ?? '')" autofocus />
    </x-ui.field>
    <x-ui.field :label="__('staff.email')" name="email" required>
        <x-ui.input name="email" type="email" :value="old('email', $staff->email ?? '')" />
    </x-ui.field>
    <x-ui.field :label="__('staff.role')" name="role_id" required class="sm:col-span-2">
        <x-ui.select name="role_id" :disabled="$isEdit && $staff->id === auth()->id()">
            @foreach ($roles as $role)
                <option value="{{ $role->id }}" @selected(old('role_id', $currentRoleId ?? null) == $role->id)>{{ $role->label }}</option>
            @endforeach
        </x-ui.select>
        @if ($isEdit && $staff->id === auth()->id())
            <input type="hidden" name="role_id" value="{{ $currentRoleId }}">
            <p class="text-xs text-muted mt-1">{{ __('staff.cannot_change_self') }}</p>
        @endif
    </x-ui.field>
</div>
