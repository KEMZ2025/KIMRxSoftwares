<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Insurers - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f5f7fb; }
        .content { flex: 1; padding: 20px; }
        .topbar, .panel { background: #fff; padding: 20px; border-radius: 16px; margin-bottom: 20px; box-shadow: 0 14px 34px rgba(15, 23, 42, 0.06); }
        .muted { color: #667085; }
        .summary-grid, .form-grid { display: grid; grid-template-columns: repeat(3, minmax(220px, 1fr)); gap: 16px; }
        .summary-card { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; }
        .summary-card span { display: block; font-size: 12px; text-transform: uppercase; letter-spacing: .05em; color: #526071; margin-bottom: 8px; }
        .summary-card strong { font-size: 28px; color: #0f172a; }
        .form-group { display: flex; flex-direction: column; }
        .form-group label { margin-bottom: 8px; font-weight: bold; }
        .form-group input, .form-group textarea { padding: 10px; border: 1px solid #d0d5dd; border-radius: 10px; }
        .btn { padding: 10px 14px; border: none; border-radius: 10px; color: #fff; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #155eef; }
        .btn-secondary { background: #0f766e; }
        .btn-muted { background: #475467; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th, td { padding: 12px; border-bottom: 1px solid #eaecf0; text-align: left; vertical-align: top; }
        th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #475467; }
        .badge { display: inline-block; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: bold; }
        .badge-active { background: #ecfdf3; color: #067647; }
        .badge-inactive { background: #fee4e2; color: #b42318; }
        .alert-success, .alert-danger { padding: 12px; border-radius: 10px; margin-bottom: 16px; }
        .alert-success { background: #e7f6ec; color: #1f7a4f; }
        .alert-danger { background: #fdecea; color: #b42318; }
        .inline-edit { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 14px; padding: 14px; margin-top: 10px; }
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .summary-grid, .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
@include('layouts.sidebar')

<div class="content" id="mainContent">
    <div class="topbar">
        <h2 style="margin:0 0 8px;">Insurers</h2>
        <p class="muted" style="margin:0;">Set up the companies you bill, then use them during insurance sale approval and claim tracking.</p>
    </div>

    <div class="summary-grid" style="margin-bottom:20px;">
        <div class="summary-card">
            <span>Active Insurers</span>
            <strong>{{ $activeCount }}</strong>
        </div>
        <div class="summary-card">
            <span>Insurance Claims</span>
            <strong>{{ $claimCount }}</strong>
        </div>
        <div class="summary-card">
            <span>Search</span>
            <strong>{{ $search !== '' ? $search : 'All' }}</strong>
        </div>
    </div>

    <div class="panel">
        @if(session('success'))
            <div class="alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert-danger">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div style="display:flex; justify-content:space-between; gap:12px; flex-wrap:wrap; align-items:center; margin-bottom:16px;">
            <div>
                <h3 style="margin:0;">Add Insurer</h3>
                <p class="muted" style="margin:6px 0 0;">This list is client-specific and available to the branch claim desk.</p>
            </div>
            <form method="GET" style="display:flex; gap:10px; flex-wrap:wrap;">
                <input type="text" name="search" value="{{ $search }}" placeholder="Search insurers..." style="padding:10px; border:1px solid #d0d5dd; border-radius:10px;">
                <button type="submit" class="btn btn-muted">Search</button>
                <a href="{{ route('insurance.insurers.index') }}" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        @if(auth()->user()?->hasPermission('insurance.manage'))
            <form method="POST" action="{{ route('insurance.insurers.store') }}">
                @csrf
                <div class="form-grid">
                    <div class="form-group">
                        <label for="name">Insurer Name</label>
                        <input type="text" id="name" name="name" value="{{ old('name') }}" required>
                    </div>
                    <div class="form-group">
                        <label for="code">Code</label>
                        <input type="text" id="code" name="code" value="{{ old('code') }}">
                    </div>
                    <div class="form-group">
                        <label for="credit_days">Credit Days</label>
                        <input type="number" id="credit_days" name="credit_days" min="0" max="365" value="{{ old('credit_days', 30) }}">
                    </div>
                    <div class="form-group">
                        <label for="contact_person">Contact Person</label>
                        <input type="text" id="contact_person" name="contact_person" value="{{ old('contact_person') }}">
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}">
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" value="{{ old('address') }}">
                    </div>
                    <div class="form-group">
                        <label for="notes">Notes</label>
                        <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>
                    </div>
                    <div class="form-group">
                        <label for="is_active">Status</label>
                        <div style="display:flex; align-items:center; gap:10px; min-height:44px;">
                            <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <span>Active and ready for claims</span>
                        </div>
                    </div>
                </div>
                <div style="margin-top:16px;">
                    <button type="submit" class="btn btn-primary">Save Insurer</button>
                </div>
            </form>
        @else
            <div class="info-box">
                <h4>Read-only Access</h4>
                <p>You can review the configured insurers here, but only insurance managers can add or update them.</p>
            </div>
        @endif
    </div>

    <div class="panel">
        <h3 style="margin:0 0 14px;">Configured Insurers</h3>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Insurer</th>
                    <th>Contact</th>
                    <th>Credit Days</th>
                    <th>Claims</th>
                    <th>Status</th>
                    <th>Update</th>
                </tr>
                </thead>
                <tbody>
                @forelse($insurers as $insurer)
                    <tr>
                        <td>
                            <strong>{{ $insurer->name }}</strong><br>
                            <span class="muted">{{ $insurer->code ?: 'No code' }}</span>
                        </td>
                        <td>
                            {{ $insurer->contact_person ?: 'No contact person' }}<br>
                            <span class="muted">{{ $insurer->phone ?: ($insurer->email ?: 'No phone/email') }}</span>
                        </td>
                        <td>{{ $insurer->credit_days }}</td>
                        <td>{{ $insurer->claim_count }}</td>
                        <td>
                            <span class="badge {{ $insurer->is_active ? 'badge-active' : 'badge-inactive' }}">
                                {{ $insurer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </td>
                        <td>
                            <details>
                                <summary style="cursor:pointer; color:#155eef;">Edit</summary>
                                @if(auth()->user()?->hasPermission('insurance.manage'))
                                <form method="POST" action="{{ route('insurance.insurers.update', $insurer) }}" class="inline-edit">
                                    @csrf
                                    @method('PUT')
                                    <div class="form-grid">
                                        <div class="form-group">
                                            <label>Name</label>
                                            <input type="text" name="name" value="{{ $insurer->name }}" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Code</label>
                                            <input type="text" name="code" value="{{ $insurer->code }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Credit Days</label>
                                            <input type="number" name="credit_days" min="0" max="365" value="{{ $insurer->credit_days }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Contact Person</label>
                                            <input type="text" name="contact_person" value="{{ $insurer->contact_person }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Phone</label>
                                            <input type="text" name="phone" value="{{ $insurer->phone }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" value="{{ $insurer->email }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Address</label>
                                            <input type="text" name="address" value="{{ $insurer->address }}">
                                        </div>
                                        <div class="form-group">
                                            <label>Notes</label>
                                            <textarea name="notes" rows="3">{{ $insurer->notes }}</textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Status</label>
                                            <div style="display:flex; align-items:center; gap:10px; min-height:44px;">
                                                <input type="checkbox" name="is_active" value="1" {{ $insurer->is_active ? 'checked' : '' }}>
                                                <span>Active</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div style="margin-top:12px;">
                                        <button type="submit" class="btn btn-primary">Update Insurer</button>
                                    </div>
                                </form>
                                @else
                                    <div class="inline-edit muted">Only insurance managers can update insurer records.</div>
                                @endif
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted" style="padding:24px; text-align:center;">No insurers added yet.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px;">
            {{ $insurers->links() }}
        </div>
    </div>
</div>
</body>
</html>
