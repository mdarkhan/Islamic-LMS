@php
    $isEdit = isset($role);
    $granted = old('permissions', $grantedIds ?? []);
@endphp

<div class="grid sm:grid-cols-2 gap-4">
    @unless ($isEdit)
        <x-ui.field :label="__('roles.name')" name="name" required :hint="__('roles.name_hint')">
            <x-ui.input name="name" :value="old('name')" placeholder="ustaz" autofocus />
        </x-ui.field>
    @endunless
    <x-ui.field :label="__('roles.label')" name="label" required>
        <x-ui.input name="label" :value="old('label', $role->label ?? '')" />
    </x-ui.field>
</div>

<div class="mt-6">
    <h3 class="font-bold text-ink mb-3">{{ __('roles.permissions_heading') }}</h3>
    <div class="space-y-4">
        @foreach ($permissionGroups as $group => $perms)
            @php $groupIds = $perms->pluck('id')->all(); @endphp
            <div x-data="{ checked: {{ \Illuminate\Support\Js::from(array_values(array_intersect($groupIds, $granted))) }} }" class="border border-line rounded-xl p-4">
                <div class="flex items-center justify-between mb-2.5">
                    <p class="text-sm font-semibold text-ink">{{ $group }}</p>
                    <label class="flex items-center gap-1.5 text-xs text-muted cursor-pointer">
                        <input type="checkbox" :checked="checked.length === {{ count($groupIds) }}"
                               @change="checked = $event.target.checked ? {{ \Illuminate\Support\Js::from($groupIds) }} : []"
                               class="rounded border-line text-brand focus:ring-brand">
                        {{ __('roles.select_all_group') }}
                    </label>
                </div>
                <div class="grid sm:grid-cols-2 gap-2">
                    @foreach ($perms as $perm)
                        <label class="flex items-center gap-2 text-sm text-ink">
                            <input type="checkbox" name="permissions[]" value="{{ $perm->id }}"
                                   :checked="checked.includes({{ $perm->id }})"
                                   @change="$event.target.checked ? checked.push({{ $perm->id }}) : checked = checked.filter(id => id !== {{ $perm->id }})"
                                   class="rounded border-line text-brand focus:ring-brand">
                            {{ $perm->label }}
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</div>
