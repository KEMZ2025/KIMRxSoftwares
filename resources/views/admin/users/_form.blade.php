@php
    $selectedRoleIds = collect(old('role_ids', isset($managedUser) ? $managedUser->roles->pluck('id')->all() : []))
        ->map(fn ($id) => (int) $id)
        ->all();
@endphp

<div class="form-grid">
    <div class="field">
        <label for="name">Full Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $managedUser->name ?? '') }}" required>
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $managedUser->email ?? '') }}" required>
        @error('email')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="branch_id">Branch</label>
        <select id="branch_id" name="branch_id" required>
            <option value="">Select branch</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}" {{ (int) old('branch_id', $managedUser->branch_id ?? '') === (int) $branch->id ? 'selected' : '' }}>
                    {{ $branch->name }}{{ $branch->is_main ? ' (Main)' : '' }}
                </option>
            @endforeach
        </select>
        @error('branch_id')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="is_active">Account Status</label>
        <select id="is_active" name="is_active">
            <option value="1" {{ old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) == 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ old('is_active', isset($managedUser) ? (int) $managedUser->is_active : 1) == 0 ? 'selected' : '' }}>Inactive</option>
        </select>
        @if(isset($userSeatSummary))
            <small class="hint">
                Seat usage: {{ $userSeatSummary['used'] }} / {{ $userSeatSummary['limit_label'] }} active users.
                @if($userSeatSummary['is_full'])
                    Active status is full right now, so only inactive saves will pass until a seat opens.
                @elseif($userSeatSummary['has_limit'])
                    Remaining seats: {{ $userSeatSummary['remaining_label'] }}.
                @else
                    This client package has no active-user cap.
                @endif
            </small>
        @endif
        @error('is_active')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="password">{{ isset($managedUser) ? 'New Password' : 'Password' }}</label>
        <input id="password" type="password" name="password" {{ isset($managedUser) ? '' : 'required' }}>
        <small class="hint">{{ isset($managedUser) ? 'Leave blank to keep the current password.' : 'Use at least 8 characters.' }}</small>
        @error('password')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="password_confirmation">{{ isset($managedUser) ? 'Confirm New Password' : 'Confirm Password' }}</label>
        <input id="password_confirmation" type="password" name="password_confirmation" {{ isset($managedUser) ? '' : 'required' }}>
    </div>
</div>

<section class="roles-panel">
    <div class="roles-head">
        <div>
            <h3>Assigned Roles</h3>
            <p>These roles decide which screens and actions this user can access.</p>
        </div>
    </div>

    <div class="roles-grid">
        @foreach($roles as $role)
            @php
                $permissionPreview = $role->permissions->pluck('action_name')->take(4)->implode(', ');
            @endphp
            <label class="role-card">
                <input
                    type="checkbox"
                    name="role_ids[]"
                    value="{{ $role->id }}"
                    {{ in_array((int) $role->id, $selectedRoleIds, true) ? 'checked' : '' }}
                >
                <div>
                    <div class="role-card-head">
                        <strong>{{ $role->name }}</strong>
                        @if($role->is_system_role)
                            <span class="pill">System</span>
                        @endif
                    </div>
                    <p>{{ $role->description ?: 'No description.' }}</p>
                    <small>{{ $role->permissions->count() }} permissions{{ $permissionPreview ? ' · ' . $permissionPreview : '' }}</small>
                </div>
            </label>
        @endforeach
    </div>
    @error('role_ids')<small class="error">{{ $message }}</small>@enderror
    @error('role_ids.*')<small class="error">{{ $message }}</small>@enderror
</section>
