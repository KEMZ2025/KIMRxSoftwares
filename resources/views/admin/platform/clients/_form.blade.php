<div class="form-grid">
    <div class="field">
        <label for="name">Client Name</label>
        <input id="name" type="text" name="name" value="{{ old('name', $managedClient->name ?? '') }}" required>
        @error('name')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="business_mode">Business Mode</label>
        <select id="business_mode" name="business_mode" required>
            @foreach($businessModes as $value => $label)
                <option value="{{ $value }}" {{ old('business_mode', $managedClient->business_mode ?? 'both') === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        @error('business_mode')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="package_preset">Package Preset</label>
        <select id="package_preset" name="package_preset">
            <option value="">Custom / Manual</option>
            @foreach(($packagePresets ?? []) as $value => $definition)
                <option value="{{ $value }}" {{ old('package_preset', $managedClient->package_preset ?? '') === $value ? 'selected' : '' }}>
                    {{ $definition['label'] }}
                </option>
            @endforeach
        </select>
        <small class="hint">Choose a package and use the apply button to fill modules and seat limits quickly. You can still fine-tune the switches afterward.</small>
        @error('package_preset')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="email">Email</label>
        <input id="email" type="email" name="email" value="{{ old('email', $managedClient->email ?? '') }}">
        @error('email')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="phone">Phone</label>
        <input id="phone" type="text" name="phone" value="{{ old('phone', $managedClient->phone ?? '') }}">
        @error('phone')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field field-span">
        <label for="address">Address</label>
        <textarea id="address" name="address" rows="3">{{ old('address', $managedClient->address ?? '') }}</textarea>
        @error('address')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="client_type">Client Type</label>
        <select id="client_type" name="client_type" required>
            @foreach(($clientTypes ?? []) as $value => $label)
                <option value="{{ $value }}" {{ old('client_type', $managedClient->client_type ?? \App\Models\Client::TYPE_PAYING) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <small class="hint">Use this to distinguish demo, trial, paying, or internal tenants in the owner workspace.</small>
        @error('client_type')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="subscription_status">Subscription Status</label>
        <select id="subscription_status" name="subscription_status" required>
            @foreach(($subscriptionStatuses ?? []) as $value => $label)
                <option value="{{ $value }}" {{ old('subscription_status', $managedClient->subscription_status ?? \App\Models\Client::STATUS_ACTIVE) === $value ? 'selected' : '' }}>
                    {{ $label }}
                </option>
            @endforeach
        </select>
        <small class="hint">This is billing/package tracking. Client suspension still depends on the Active/Inactive switch below.</small>
        @error('subscription_status')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="active_user_limit">Active User Limit</label>
        <input
            id="active_user_limit"
            type="number"
            name="active_user_limit"
            min="1"
            step="1"
            value="{{ old('active_user_limit', $managedClient->active_user_limit ?? '') }}"
            placeholder="Leave blank for unlimited"
        >
        <small class="hint">Only active tenant users count. Leave empty if this client package should have unlimited seats.</small>
        @error('active_user_limit')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="subscription_ends_at">Subscription End Date</label>
        <input
            id="subscription_ends_at"
            type="date"
            name="subscription_ends_at"
            value="{{ old('subscription_ends_at', isset($managedClient) && $managedClient->subscription_ends_at ? $managedClient->subscription_ends_at->format('Y-m-d') : '') }}"
        >
        <small class="hint">Optional renewal date for tracking trials, grace periods, or annual subscriptions.</small>
        @error('subscription_ends_at')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field">
        <label for="is_active">Status</label>
        <select id="is_active" name="is_active">
            <option value="1" {{ (int) old('is_active', isset($managedClient) ? (int) $managedClient->is_active : 1) === 1 ? 'selected' : '' }}>Active</option>
            <option value="0" {{ (int) old('is_active', isset($managedClient) ? (int) $managedClient->is_active : 1) === 0 ? 'selected' : '' }}>Inactive</option>
        </select>
        <small class="hint">This is the hard on/off switch that blocks or allows tenant access to the system.</small>
        @error('is_active')<small class="error">{{ $message }}</small>@enderror
    </div>

    <div class="field field-span">
        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:flex-start; padding:16px; border:1px solid #dbe3ef; border-radius:16px; background:#f8fafc;">
            <div>
                <strong style="display:block; margin-bottom:6px;">Package Quick Apply</strong>
                <div id="packagePresetSummary" class="hint" style="margin-top:0;">Select a preset to preview its seat limit and access mix. Trials without an end date will default to 14 days.</div>
            </div>
            <button type="button" class="btn btn-secondary" id="applyPackagePresetButton">Apply Package Preset</button>
        </div>
    </div>

    <div class="field field-span">
        <small class="hint">Package tip: use Client Type + Subscription Status + Active User Limit to describe what the client is paying for, then use the module switches below to control the exact feature access they receive.</small>
    </div>
</div>

@php
    $moduleOptions = collect($moduleOptions ?? []);
    $accountingModuleOption = $moduleOptions->firstWhere('field', 'accounts_enabled');
@endphp

<h3 class="section-title">Paid Module Access</h3>
<p class="section-copy">Choose which product areas this client can access based on their package or agreement. Accounting is configured in the section below.</p>

<div class="feature-grid">
    @foreach($moduleOptions->reject(fn ($option) => ($option['field'] ?? null) === 'accounts_enabled') as $option)
        @php
            $checked = (int) old($option['field'], (int) (($featureValues[$option['field']] ?? false) ? 1 : 0)) === 1;
        @endphp
        <label class="feature-card">
            <input type="hidden" name="{{ $option['field'] }}" value="0">
            <span class="feature-check">
                <input type="checkbox" name="{{ $option['field'] }}" value="1" {{ $checked ? 'checked' : '' }}>
                <span>{{ $option['label'] }}</span>
            </span>
            <span class="feature-description">{{ $option['description'] }}</span>
        </label>
    @endforeach
</div>

<h3 class="section-title">Accounting Access Detail</h3>
<p class="section-copy">Turn the accounting workspace on or off for this client, then choose exactly which accounting modules the package includes.</p>

@if($accountingModuleOption)
    @php
        $accountingEnabled = (int) old($accountingModuleOption['field'], (int) (($featureValues[$accountingModuleOption['field']] ?? false) ? 1 : 0)) === 1;
    @endphp
    <div class="feature-grid" style="margin-bottom:14px;">
        <label class="feature-card">
            <input type="hidden" name="{{ $accountingModuleOption['field'] }}" value="0">
            <span class="feature-check">
                <input type="checkbox" name="{{ $accountingModuleOption['field'] }}" value="1" {{ $accountingEnabled ? 'checked' : '' }}>
                <span>Accounting Module</span>
            </span>
            <span class="feature-description">Enable or disable the accounting workspace for this client. When it is enabled, the switches below control which accounting screens they can access.</span>
        </label>
    </div>
@endif

<div class="feature-grid feature-grid-tight" id="accountingFeatureGrid">
    @foreach(($accountingFeatureOptions ?? []) as $option)
        @php
            $checked = (int) old($option['field'], (int) (($featureValues[$option['field']] ?? false) ? 1 : 0)) === 1;
        @endphp
        <label class="feature-card feature-card-compact">
            <input type="hidden" name="{{ $option['field'] }}" value="0">
            <span class="feature-check">
                <input type="checkbox" name="{{ $option['field'] }}" value="1" {{ $checked ? 'checked' : '' }}>
                <span>{{ $option['label'] }}</span>
            </span>
            <span class="feature-description">{{ $option['description'] }}</span>
        </label>
    @endforeach
</div>

<script type="application/json" id="packagePresetData">
{!! json_encode($packagePresets ?? [], JSON_PRETTY_PRINT) !!}
</script>
