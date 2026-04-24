<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Units - KIM Rx Softwares</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #222; }
        .layout { display: flex; min-height: 100vh; }

        .content { flex: 1; padding: 24px; }

        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            background: white;
            padding: 16px 20px;
            border-radius: 14px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .welcome h3 { margin: 0; }
        .welcome p { margin: 6px 0 0; color: #666; }

        .logout-form button {
            border: none;
            background: #d32f2f;
            color: white;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
        }

        .panel {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.06);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 10px 16px;
            border-radius: 10px;
            color: white;
            background: #1f7a4f;
        }

        .btn-edit {
            background: #6a1b9a;
            padding: 8px 12px;
            font-size: 13px;
        }

        .btn-delete {
            background: #d32f2f;
            padding: 8px 12px;
            font-size: 13px;
        }

        .action-group {
            display: flex;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
        }

        .inline-form {
            display: inline;
            margin: 0;
        }

        .search-form { margin-bottom: 18px; }

        .search-form input {
            width: 100%;
            max-width: 350px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            outline: none;
        }

        .alert-success {
            background: #e7f6ec;
            color: #1f7a4f;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table th, table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
        }

        table th { background: #fafafa; }

        .empty {
            text-align: center;
            padding: 20px;
            color: #777;
        }

        .pagination { margin-top: 18px; }

        .status-active {
            color: #1f7a4f;
            font-weight: bold;
        }

        .status-inactive {
            color: #d32f2f;
            font-weight: bold;
        }

        @media (max-width: 900px) {
            .layout { flex-direction: column; }
            .topbar { flex-direction: column; gap: 12px; align-items: flex-start; }
            .content { overflow-x: auto; }
        }
    </style>
</head>
<body>
    <div class="layout">
        @include('layouts.sidebar')

    <main class="content" id="mainContent">
            <div class="topbar">
                <div class="welcome">
                    <h3>Welcome, {{ $user->name }}</h3>
                    <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
                </div>

                <form class="logout-form" method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit">Logout</button>
                </form>
            </div>

            <div class="panel">
                <div class="page-header">
                    <div>
                        <h2 style="margin:0;">Units List</h2>
                        <p style="margin:6px 0 0; color:#666;">Manage product units in KIM Rx</p>
                    </div>

                    <a href="{{ route('units.create') }}" class="btn">Add Unit</a>
                </div>

                @if(session('success'))
                    <div class="alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="GET" action="{{ route('units.index') }}" class="search-form">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search by unit name or description..."
                        value="{{ request('search') }}"
                    >
                </form>

                <div style="overflow-x:auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Unit Name</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($units as $unit)
                                <tr>
                                    <td>{{ $loop->iteration + ($units->currentPage() - 1) * $units->perPage() }}</td>
                                    <td>{{ $unit->name }}</td>
                                    <td>{{ $unit->description ?? 'N/A' }}</td>
                                    <td>
                                        @if($unit->is_active)
                                            <span class="status-active">Active</span>
                                        @else
                                            <span class="status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-group">
                                            <a href="{{ route('units.edit', $unit->id) }}" class="btn btn-edit">Edit</a>

                                            <form method="POST" action="{{ route('units.destroy', $unit->id) }}" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this unit?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="empty">No units found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="pagination">
                    {{ $units->withQueryString()->links() }}
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleDropdown(id) {
            document.getElementById(id).classList.toggle('show');
        }
    </script>
</body>
</html>
