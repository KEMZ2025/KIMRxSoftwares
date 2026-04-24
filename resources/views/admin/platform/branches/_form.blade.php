<div class="form-grid">
    <div class="field">
        <label for="name">Branch Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $managedBranch->name ?? '') }}" required>
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="code">Branch Code</label>
        <input id="code" type="text" name="code" value="{{ old('code', $managedBranch->code ?? '') }}">
        @error('code')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="email">Branch Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $managedBranch->email ?? '') }}">
        @error('email')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="phone">Branch Phone</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $managedBranch->phone ?? '') }}">
        @error('phone')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field field-span">
        <label for="address">Branch Address</label>
        <textarea id="address" name="address" rows="3">{{ old('address', $managedBranch->address ?? '') }}</textarea>
        @error('address')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="business_mode">Branch Mode</label>
        <select id="business_mode" name="business_mode">
            @foreach($branchBusinessModes as $value => $label)
                <option value="{{ $value }}" {{ old('business_mode', $managedBranch->business_mode ?? 'inherit') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <small class="hint">Use `Inherit Client Mode` to follow the client setting, or choose a narrower branch mode when the client allows it.</small>
        @error('business_mode')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="is_main">Main Branch</label>
        <select id="is_main" name="is_main">
            <option value="0" {{ (int) old('is_main', isset($managedBranch) ? (int) $managedBranch->is_main : 0) === 0 ? 'selected' : '' }}>No</option>
            <option value="1" {{ (int) old('is_main', isset($managedBranch) ? (int) $managedBranch->is_main : 0) === 1 ? 'selected' : '' }}>Yes</option>
        </select>
        @error('is_main')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="is_active">Status</label>
        <select id="is_active" name="is_active">
            <option value="1" {{ (int) old('is_active', isset($managedBranch) ? (int) $managedBranch->is_active : 1) === 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (int) old('is_active', isset($managedBranch) ? (int) $managedBranch->is_active : 1) === 0 ? 'selected' : '' }}>Inactive</option>
        </select>
        @error('is_active')<small class="error">{{ $message }}</small>@enderror
    </div>
</div>
