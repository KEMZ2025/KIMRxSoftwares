<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Import - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            background:
                radial-gradient(circle at top right, rgba(15, 118, 110, 0.08), transparent 30%),
                linear-gradient(180deg, #f8fafc 0%, #eef2ff 100%);
            color: #172033;
        }
        .content {
            flex: 1;
            width: 100%;
            max-width: 100%;
            padding: 20px;
        }
        .topbar,
        .panel,
        .card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            backdrop-filter: blur(10px);
        }
        .topbar,
        .panel {
            margin-bottom: 20px;
        }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
        }
        .eyebrow {
            margin: 0 0 8px;
            color: #475467;
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .topbar h1 {
            margin: 0 0 8px;
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.05;
        }
        .topbar p,
        .panel-subtitle,
        .card p,
        .stat-note {
            margin: 0;
            color: #667085;
            font-size: 14px;
            line-height: 1.55;
        }
        .chips {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: flex-end;
        }
        .chip {
            padding: 12px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #e0ecff, #eef4ff);
            color: #1d4ed8;
            font-weight: 800;
            white-space: nowrap;
            font-size: 13px;
        }
        .chip.alt {
            background: linear-gradient(135deg, #ecfdf3, #f0fdf4);
            color: #067647;
        }
        .banner {
            border-radius: 16px;
            padding: 16px 18px;
            margin-bottom: 16px;
            border: 1px solid transparent;
        }
        .banner.success {
            background: #ecfdf3;
            color: #067647;
            border-color: #abefc6;
        }
        .banner.error {
            background: #fef3f2;
            color: #b42318;
            border-color: #fecdca;
        }
        .banner strong {
            display: block;
            margin-bottom: 6px;
        }
        .note-list,
        .error-list {
            margin: 10px 0 0;
            padding-left: 18px;
        }
        .section-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        .card h2,
        .panel h2 {
            margin: 0 0 8px;
            font-size: 22px;
        }
        .card h3 {
            margin: 0 0 10px;
            font-size: 18px;
        }
        .card-actions,
        .preview-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 16px;
        }
        .btn,
        .btn-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            padding: 12px 16px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            border: 1px solid transparent;
        }
        .btn-primary {
            background: linear-gradient(135deg, #155eef, #004eeb);
            color: #fff;
        }
        .btn-secondary {
            background: #fff;
            color: #344054;
            border-color: #d0d5dd;
        }
        .btn-danger {
            background: #fff1f3;
            color: #b42318;
            border-color: #fecdca;
        }
        .upload-box {
            margin-top: 14px;
            padding: 14px;
            border-radius: 16px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
        }
        .upload-box label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 700;
            color: #344054;
        }
        .upload-box input[type="file"] {
            width: 100%;
            border: 1px solid #d0d5dd;
            border-radius: 12px;
            padding: 12px;
            background: #fff;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-top: 18px;
        }
        .stat-card {
            padding: 16px;
            border-radius: 16px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e4e7ec;
        }
        .stat-card strong {
            display: block;
            font-size: 28px;
            margin-top: 8px;
        }
        .table-wrap {
            overflow-x: auto;
            margin-top: 18px;
            border: 1px solid #e4e7ec;
            border-radius: 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 760px;
        }
        th,
        td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid #eaecf0;
            vertical-align: top;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            color: #475467;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-create {
            background: #ecfdf3;
            color: #067647;
        }
        .status-update {
            background: #eff8ff;
            color: #175cd3;
        }
        .status-error {
            background: #fef3f2;
            color: #b42318;
        }
        .muted {
            color: #667085;
        }
        .scope-box {
            border-radius: 16px;
            padding: 16px;
            background: linear-gradient(180deg, #fffbeb, #fff7ed);
            border: 1px solid #fedf89;
        }
        .scope-box strong {
            display: block;
            margin-bottom: 8px;
            color: #92400e;
        }
        @media (max-width: 980px) {
            .section-grid,
            .stat-grid {
                grid-template-columns: 1fr;
            }
            .topbar {
                flex-direction: column;
            }
            .chips {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    @include('layouts.sidebar')

    @php
        $previewAvailable = is_array($preview);
        $previewHasErrors = $previewAvailable
            && (((array) ($preview['missing_headers'] ?? [])) !== []
            || (int) (($preview['summary']['invalid_rows'] ?? 0)) > 0);
        $previewCanImport = $previewAvailable
            && !$previewHasErrors
            && (int) (($preview['summary']['valid_rows'] ?? 0)) > 0;
    @endphp

    <main class="content" id="mainContent">
        <div class="topbar">
            <div>
                <p class="eyebrow">Data Import Center</p>
                <h1>Bring Data From Previous Systems Safely</h1>
                <p>Download the KIM Rx template, paste data from the old system into the same columns, upload the CSV, preview it, then import only when everything is clean.</p>
            </div>
            <div class="chips">
                <div class="chip">{{ $clientName }}</div>
                <div class="chip alt">{{ $branchName }}</div>
            </div>
        </div>

        @if (session('success'))
            <div class="banner success">
                <strong>Success</strong>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="banner error">
                <strong>Import Error</strong>
                {{ $errors->first() }}
            </div>
        @endif

        @if (is_array($importSummary))
            <section class="panel">
                <h2>Last Import Summary</h2>
                <p class="panel-subtitle">{{ $importSummary['label'] }} imported from {{ $importSummary['file_name'] ?? 'CSV file' }}.</p>
                <div class="stat-grid">
                    <div class="stat-card">
                        <span class="muted">Rows Imported</span>
                        <strong>{{ number_format((int) ($importSummary['row_count'] ?? 0)) }}</strong>
                    </div>
                    <div class="stat-card">
                        <span class="muted">Created</span>
                        <strong>{{ number_format((int) ($importSummary['stats']['created'] ?? 0)) }}</strong>
                    </div>
                    <div class="stat-card">
                        <span class="muted">Updated</span>
                        <strong>{{ number_format((int) ($importSummary['stats']['updated'] ?? 0)) }}</strong>
                    </div>
                    <div class="stat-card">
                        <span class="muted">Lookup Records Added</span>
                        <strong>{{ number_format((int) (($importSummary['stats']['categories_created'] ?? 0) + ($importSummary['stats']['customers_created'] ?? 0) + ($importSummary['stats']['units_created'] ?? 0) + ($importSummary['stats']['suppliers_created'] ?? 0))) }}</strong>
                    </div>
                </div>
            </section>
        @endif

        <section class="panel scope-box">
            <strong>Current Scope</strong>
            This import center now handles <strong>medicines</strong>, <strong>customers</strong>, <strong>suppliers</strong>, <strong>opening stock</strong>, <strong>opening receivables</strong>, and <strong>opening payables</strong>. Full historical sales and purchase archives are still kept out on purpose so live stock, profit, and books stay clean.
        </section>

        <section class="section-grid">
            @foreach ($datasets as $key => $dataset)
                <article class="card">
                    <h3>{{ $dataset['label'] }}</h3>
                    <p>{{ $dataset['description'] }}</p>

                    <ul class="note-list">
                        @foreach ($dataset['notes'] as $note)
                            <li>{{ $note }}</li>
                        @endforeach
                    </ul>

                    <div class="card-actions">
                        <a href="{{ route('admin.imports.template', $key) }}" class="btn btn-secondary">Download Template</a>
                    </div>

                    <form method="POST" action="{{ route('admin.imports.preview') }}" enctype="multipart/form-data" class="upload-box">
                        @csrf
                        <input type="hidden" name="dataset" value="{{ $key }}">
                        <label for="import_file_{{ $key }}">Upload Filled CSV Template</label>
                        <input id="import_file_{{ $key }}" type="file" name="import_file" accept=".csv,.txt" required>
                        <div class="card-actions">
                            <button type="submit" class="btn btn-primary">Preview {{ $dataset['label'] }}</button>
                        </div>
                    </form>
                </article>
            @endforeach
        </section>

        @if ($previewAvailable)
            <section class="panel">
                <h2>Preview: {{ $preview['label'] }}</h2>
                <p class="panel-subtitle">
                    File: <strong>{{ $preview['file_name'] }}</strong>
                    <span class="muted">| Prepared at {{ $preview['generated_at'] }}</span>
                </p>

                @if (($preview['missing_headers'] ?? []) !== [])
                    <div class="banner error">
                        <strong>Template Columns Missing</strong>
                        The uploaded file is missing the exact template columns below:
                        <ul class="error-list">
                            @foreach ($preview['missing_headers'] as $header)
                                <li>{{ $header }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="stat-grid">
                    <div class="stat-card">
                        <span class="muted">Rows Ready</span>
                        <strong>{{ number_format((int) ($preview['summary']['row_count'] ?? 0)) }}</strong>
                    </div>
                    <div class="stat-card">
                        <span class="muted">Valid Rows</span>
                        <strong>{{ number_format((int) ($preview['summary']['valid_rows'] ?? 0)) }}</strong>
                    </div>
                    <div class="stat-card">
                        <span class="muted">Invalid Rows</span>
                        <strong>{{ number_format((int) ($preview['summary']['invalid_rows'] ?? 0)) }}</strong>
                    </div>
                    <div class="stat-card">
                        <span class="muted">Will Create / Update</span>
                        <strong>{{ number_format((int) ($preview['summary']['create_count'] ?? 0)) }} / {{ number_format((int) ($preview['summary']['update_count'] ?? 0)) }}</strong>
                    </div>
                </div>

                <div class="preview-actions">
                    @if ($previewCanImport)
                        <form method="POST" action="{{ route('admin.imports.store') }}">
                            @csrf
                            <button type="submit" class="btn btn-primary">Run Import Now</button>
                        </form>
                    @endif

                    <form method="POST" action="{{ route('admin.imports.clear') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Clear Preview</button>
                    </form>
                </div>

                @if ($previewHasErrors)
                    <div class="banner error" style="margin-top:18px;">
                        <strong>Fix Errors Before Import</strong>
                        Rows with errors are blocked. Update the CSV using the template columns, then preview again.
                    </div>
                @endif

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Row</th>
                                @foreach ($preview['preview_columns'] as $column)
                                    <th>{{ str_replace('_', ' ', ucfirst($column)) }}</th>
                                @endforeach
                                <th>Errors</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($preview['rows'] as $row)
                                <tr>
                                    <td>{{ $row['row_number'] }}</td>
                                    @foreach ($preview['preview_columns'] as $column)
                                        <td>
                                            @if ($column === 'operation')
                                                @php
                                                    $operation = $row['display'][$column] ?? '';
                                                @endphp
                                                <span class="status-pill {{ $operation === 'update' ? 'status-update' : 'status-create' }}">
                                                    {{ ucfirst($operation ?: 'create') }}
                                                </span>
                                            @else
                                                {{ $row['display'][$column] ?? '' }}
                                            @endif
                                        </td>
                                    @endforeach
                                    <td>
                                        @if (($row['errors'] ?? []) === [])
                                            <span class="status-pill status-create">Ready</span>
                                        @else
                                            <span class="status-pill status-error">Blocked</span>
                                            <ul class="error-list">
                                                @foreach ($row['errors'] as $error)
                                                    <li>{{ $error }}</li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($preview['preview_columns']) + 2 }}">No non-empty rows were found in the uploaded file.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </main>
</body>
</html>
