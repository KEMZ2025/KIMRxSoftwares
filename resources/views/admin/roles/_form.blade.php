<div class="form-grid">
    <div class="field">
        <label for="name">Role Name</label>
        <input
            id="name"
            type="text"
            name="name"
            value="{{ old('name', $role->name ?? '') }}"
            {{ isset($role) && $role->isProtectedAdminRole() ? 'disabled' : 'required' }}
        >
        @if(isset($role) && $role->isProtectedAdminRole())
            <small class="hint">The Admin role name is fixed because it keeps full system access.</small>
        @endif
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="description">Description</label>
        <input id="description" type="text" name="description" value="{{ old('description', $role->description ?? '') }}">
        @error('description')<small class="error">{{ $message }}</small>@enderror
    </div>
</div>

<section class="permissions-panel">
    <div class="permissions-head">
        <div>
            <h3>Screen Permissions</h3>
            <p>Select the screens and actions this role should be allowed to use.</p>
        </div>
    </div>

    @foreach($permissionGroups as $moduleName => $permissions)
        <div class="permission-group">
            <h4>{{ $moduleName }}</h4>
            @if($moduleName === 'Sensitive Sales Controls')
                <p class="hint" style="margin:6px 0 14px;">
                    These permissions change invoice status, stock flow, or audit history. Assign them only to trusted users like supervisors or admins.
                </p>
            @endif
            <div class="permission-grid">
                @foreach($permissions as $permissionKey => $permission)
                    <label class="permission-card">
                        <input
                            type="checkbox"
                            name="permission_keys[]"
                            value="{{ $permissionKey }}"
                            {{ in_array($permissionKey, old('permission_keys', $selectedPermissionKeys ?? []), true) ? 'checked' : '' }}
                            {{ isset($role) && $role->isProtectedAdminRole() ? 'disabled' : '' }}
                        >
                        <div>
                            <strong>{{ $permission['action'] }}</strong>
                            <p>{{ $permission['description'] }}</p>
                            <small>{{ $permissionKey }}</small>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>
    @endforeach

    @error('permission_keys')<small class="error">{{ $message }}</small>@enderror
    @error('permission_keys.*')<small class="error">{{ $message }}</small>@enderror
</section>
