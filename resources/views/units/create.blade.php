<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Unit - KIM Rx Softwares</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            padding: 24px;
        }

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

        .welcome h3 {
            margin: 0;
        }

        .welcome p {
            margin: 6px 0 0;
            color: #666;
        }

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
            margin-bottom: 20px;
        }

        .page-header h2 {
            margin: 0 0 8px;
        }

        .page-header p {
            margin: 0;
            color: #666;
        }

        .alert {
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .alert-danger {
            background: #fdecea;
            color: #b42318;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        label {
            font-weight: bold;
            margin-bottom: 8px;
        }

        input, textarea {
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
            font-size: 14px;
            outline: none;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .checkbox-group input {
            width: auto;
        }

        .actions {
            margin-top: 24px;
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-block;
            text-decoration: none;
            border: none;
            cursor: pointer;
            padding: 12px 18px;
            border-radius: 10px;
            color: white;
            background: #1f7a4f;
        }

        .btn-secondary {
            background: #666;
        }

        @media (max-width: 900px) {
            .layout {
                flex-direction: column;
            }

            .topbar {
                flex-direction: column;
                gap: 12px;
                align-items: flex-start;
            }
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
                    <h2>Add Unit</h2>
                    <p>Enter the unit details below.</p>
                </div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Please fix the following errors:</strong>
                        <ul style="margin:10px 0 0 18px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('units.store') }}">
                    @csrf

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Unit Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required>
                        </div>

                        <div class="form-group">
                            <label for="description">Description</label>
                            <textarea name="description" id="description">{{ old('description') }}</textarea>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" name="is_active" id="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }}>
                            <label for="is_active" style="margin:0;">Active Unit</label>
                        </div>
                    </div>

                    <div class="actions">
                        <button type="submit" class="btn">Save Unit</button>
                        <a href="{{ route('units.index') }}" class="btn btn-secondary">Back to Units</a>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
