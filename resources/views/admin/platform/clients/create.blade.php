<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Client - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f4f6fb; color: #1f2937; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; padding: 24px; margin-left: 260px; transition: margin-left 0.3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border-radius: 18px; padding: 22px; box-shadow: 0 10px 28px rgba(0, 0, 0, 0.06); margin-bottom: 22px; }
        .panel-head { display:flex; justify-content:space-between; align-items:flex-start; gap:14px; flex-wrap:wrap; margin-bottom:18px; }
        .btn { display:inline-flex; align-items:center; justify-content:center; padding:10px 14px; border-radius:10px; border:none; color:#fff; text-decoration:none; cursor:pointer; font-weight:700; }
        .btn-back { background:#3949ab; }
        .btn-save { background:#1f7a4f; }
        .btn-secondary { background:#2563eb; }
        .form-grid { display:grid; grid-template-columns: repeat(2, minmax(240px, 1fr)); gap:16px; }
        .field { display:block; }
        .field-span { grid-column: 1 / -1; }
        .field label { display:block; margin-bottom:6px; font-weight:700; }
        .field input, .field select, .field textarea { width:100%; padding:12px 14px; border:1px solid #dbe3ef; border-radius:12px; }
        .section-title { margin:22px 0 14px; padding-top:22px; border-top:1px solid #e5e7eb; }
        .section-copy { margin:0 0 14px; color:#64748b; }
        .feature-grid { display:grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap:14px; }
        .feature-grid-tight { grid-template-columns: repeat(3, minmax(200px, 1fr)); }
        .feature-card { display:grid; gap:10px; padding:16px; border:1px solid #dbe3ef; border-radius:16px; background:#f8fafc; }
        .feature-card-compact { min-height: 138px; }
        .feature-check { display:flex; align-items:center; gap:10px; font-weight:800; color:#172033; }
        .feature-check input { width:18px; height:18px; }
        .feature-description { color:#64748b; font-size:13px; line-height:1.5; }
        .feature-grid.is-disabled { opacity:0.55; }
        .hint { display:block; margin-top:6px; color:#64748b; font-size:12px; }
        .error { display:block; margin-top:6px; color:#b91c1c; font-size:12px; font-weight:700; }
        @media (max-width: 900px) {
            .layout { display:block; }
            .content, .content.expanded { margin-left:0; padding:16px; }
            .form-grid, .feature-grid, .feature-grid-tight { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')

    <main class="content" id="mainContent">
        <section class="topbar">
            <h3 style="margin:0 0 8px;">Add Client</h3>
            <p style="margin:0; color:#64748b;">Create a client and its first main branch in one step.</p>
        </section>

        <section class="panel">
            <div class="panel-head">
                <div>
                    <h2 style="margin:0 0 6px;">New Client</h2>
                    <p style="margin:0; color:#64748b;">The system will also prepare the default roles for this client automatically.</p>
                </div>
                <a href="{{ route('admin.platform.clients.index') }}" class="btn btn-back">Back to Clients</a>
            </div>

            <form method="POST" action="{{ route('admin.platform.clients.store') }}">
                @csrf

                @include('admin.platform.clients._form')

                <h3 class="section-title">Initial Main Branch</h3>

                <div class="form-grid">
                    <div class="field">
                        <label for="initial_branch_name">Branch Name</label>
                        <input id="initial_branch_name" type="text" name="initial_branch_name" value="{{ old('initial_branch_name') }}" required>
                        @error('initial_branch_name')<small class="error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="initial_branch_code">Branch Code</label>
                        <input id="initial_branch_code" type="text" name="initial_branch_code" value="{{ old('initial_branch_code') }}">
                        @error('initial_branch_code')<small class="error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="initial_branch_email">Branch Email</label>
                        <input id="initial_branch_email" type="email" name="initial_branch_email" value="{{ old('initial_branch_email') }}">
                        @error('initial_branch_email')<small class="error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="initial_branch_phone">Branch Phone</label>
                        <input id="initial_branch_phone" type="text" name="initial_branch_phone" value="{{ old('initial_branch_phone') }}">
                        @error('initial_branch_phone')<small class="error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="initial_branch_business_mode">Branch Mode</label>
                        <select id="initial_branch_business_mode" name="initial_branch_business_mode">
                            @foreach($initialBranchBusinessModes as $value => $label)
                                <option value="{{ $value }}" {{ old('initial_branch_business_mode', 'inherit') === $value ? 'selected' : '' }}>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        <small class="hint">For a `Both` client, this first branch can stay `Both` or be narrowed to retail-only or wholesale-only.</small>
                        @error('initial_branch_business_mode')<small class="error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field field-span">
                        <label for="initial_branch_address">Branch Address</label>
                        <textarea id="initial_branch_address" name="initial_branch_address" rows="3">{{ old('initial_branch_address') }}</textarea>
                        @error('initial_branch_address')<small class="error">{{ $message }}</small>@enderror
                    </div>

                    <div class="field">
                        <label for="initial_branch_is_active">Branch Status</label>
                        <select id="initial_branch_is_active" name="initial_branch_is_active">
                            <option value="1" {{ (int) old('initial_branch_is_active', 1) === 1 ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ (int) old('initial_branch_is_active', 1) === 0 ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>

                <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                    <button type="submit" class="btn btn-save">Create Client</button>
                </div>
            </form>
        </section>
    </main>
</div>

<script>
(() => {
    const clientModeSelect = document.getElementById('business_mode');
    const branchModeSelect = document.getElementById('initial_branch_business_mode');
    const accountingToggle = document.querySelector('input[name="accounts_enabled"]');
    const accountingGrid = document.getElementById('accountingFeatureGrid');
    const packagePresetSelect = document.getElementById('package_preset');
    const applyPackagePresetButton = document.getElementById('applyPackagePresetButton');
    const packagePresetSummary = document.getElementById('packagePresetSummary');
    const activeUserLimitInput = document.getElementById('active_user_limit');
    const packagePresetDataElement = document.getElementById('packagePresetData');
    const packagePresets = packagePresetDataElement ? JSON.parse(packagePresetDataElement.textContent || '{}') : {};
    const branchOptionsByClientMode = {
        retail_only: {
            inherit: 'Inherit Client Mode',
            retail_only: 'Retail Only'
        },
        wholesale_only: {
            inherit: 'Inherit Client Mode',
            wholesale_only: 'Wholesale Only'
        },
        both: {
            inherit: 'Inherit Client Mode',
            retail_only: 'Retail Only',
            wholesale_only: 'Wholesale Only',
            both: 'Retail and Wholesale'
        }
    };
    const selectedBranchMode = @json(old('initial_branch_business_mode', 'inherit'));

    function renderBranchModes(mode, preferred) {
        const options = branchOptionsByClientMode[mode] || branchOptionsByClientMode.both;
        branchModeSelect.innerHTML = '';

        Object.entries(options).forEach(([value, label], index) => {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            option.selected = value === preferred || (!Object.keys(options).includes(preferred) && index === 0);
            branchModeSelect.appendChild(option);
        });
    }

    function syncAccountingFeatureState() {
        if (!accountingToggle || !accountingGrid) {
            return;
        }

        const enabled = accountingToggle.checked;
        accountingGrid.classList.toggle('is-disabled', !enabled);

        accountingGrid.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            checkbox.disabled = !enabled;
        });
    }

    function syncPackagePresetSummary() {
        if (!packagePresetSummary || !packagePresetSelect) {
            return;
        }

        const preset = packagePresets[packagePresetSelect.value];
        if (!preset) {
            packagePresetSummary.textContent = 'Select a preset to preview its seat limit and access mix. Trials without an end date will default to 14 days.';
            return;
        }

        const seatLabel = preset.active_user_limit ? `${preset.active_user_limit} active user seat(s)` : 'Unlimited active users';
        packagePresetSummary.textContent = `${preset.label}: ${preset.description} This preset applies ${seatLabel}.`;
    }

    function applyPackagePreset() {
        const preset = packagePresets[packagePresetSelect?.value];
        if (!preset) {
            return;
        }

        if (activeUserLimitInput) {
            activeUserLimitInput.value = preset.active_user_limit ?? '';
        }

        const featureValues = preset.feature_values || {};
        Object.entries(featureValues).forEach(([field, enabled]) => {
            const checkbox = document.querySelector(`input[type="checkbox"][name="${field}"]`);
            if (checkbox) {
                checkbox.disabled = false;
                checkbox.checked = !!enabled;
            }
        });

        syncAccountingFeatureState();
        syncPackagePresetSummary();
    }

    clientModeSelect.addEventListener('change', () => {
        renderBranchModes(clientModeSelect.value, branchModeSelect.value);
    });

    if (accountingToggle) {
        accountingToggle.addEventListener('change', syncAccountingFeatureState);
    }

    if (packagePresetSelect) {
        packagePresetSelect.addEventListener('change', syncPackagePresetSummary);
    }

    if (applyPackagePresetButton) {
        applyPackagePresetButton.addEventListener('click', applyPackagePreset);
    }

    renderBranchModes(clientModeSelect.value, selectedBranchMode);
    syncAccountingFeatureState();
    syncPackagePresetSummary();
})();
</script>
</body>
</html>
