<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            background:
                radial-gradient(circle at top right, rgba(59, 130, 246, 0.08), transparent 28%),
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
        .panel {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 18px 42px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(226, 232, 240, 0.9);
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
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
            margin: 0 0 6px;
            font-size: clamp(28px, 3vw, 42px);
            line-height: 1.05;
        }
        .topbar p {
            margin: 0;
            color: #667085;
            font-size: 15px;
        }
        .range-chip {
            padding: 12px 16px;
            border-radius: 999px;
            background: linear-gradient(135deg, #e0ecff, #eef4ff);
            color: #1d4ed8;
            font-weight: 800;
            white-space: nowrap;
        }
        .tabbar {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .tabbar-wrap {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }
        .tabbar a {
            text-decoration: none;
            color: #344054;
            padding: 10px 15px;
            border-radius: 999px;
            border: 1px solid #d0d5dd;
            background: #fff;
            font-weight: 700;
            font-size: 13px;
        }
        .tabbar a.active {
            background: #0f172a;
            color: #fff;
            border-color: #0f172a;
        }
        .tabbar-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
        }
        .filter-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        .filter-form input,
        .filter-form select {
            min-width: 160px;
            padding: 11px 12px;
            border-radius: 12px;
            border: 1px solid #d0d5dd;
            background: #fff;
        }
        .btn {
            border: none;
            border-radius: 12px;
            padding: 11px 16px;
            cursor: pointer;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary { background: #155eef; color: #fff; }
        .btn-light { background: #eef4ff; color: #1d4ed8; }
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }
        .summary-card {
            border-radius: 18px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1px solid #e5e7eb;
            min-height: 128px;
        }
        .summary-card .label {
            color: #667085;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .summary-card .value {
            margin-top: 14px;
            font-size: clamp(24px, 2.6vw, 31px);
            font-weight: 800;
            line-height: 1.1;
            word-break: break-word;
        }
        .summary-card .meta {
            margin-top: 10px;
            color: #475467;
            font-size: 13px;
        }
        .tone-blue { border-top: 4px solid #2563eb; }
        .tone-amber { border-top: 4px solid #f59e0b; }
        .tone-violet { border-top: 4px solid #7c3aed; }
        .tone-emerald { border-top: 4px solid #12b76a; }
        .tone-rose { border-top: 4px solid #e11d48; }
        .tone-teal { border-top: 4px solid #0f766e; }
        .tone-slate { border-top: 4px solid #475467; }
        .category-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 16px;
        }
        .category-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 10px;
            min-height: 140px;
            padding: 18px;
            border-radius: 18px;
            border: 1px solid #d0d5dd;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            text-decoration: none;
            color: #172033;
        }
        .category-card .category-title {
            font-size: 22px;
            font-weight: 800;
        }
        .category-card .category-meta {
            color: #667085;
            font-size: 14px;
        }
        .category-card .category-link {
            color: #155eef;
            font-weight: 700;
            font-size: 13px;
        }
        .two-up {
            display: grid;
            grid-template-columns: minmax(0, 1.35fr) minmax(320px, 0.95fr);
            gap: 20px;
        }
        .panel h2 {
            margin: 0 0 8px;
            font-size: 23px;
        }
        .panel .panel-subtitle {
            margin: 0 0 18px;
            color: #667085;
            font-size: 14px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        .data-table th,
        .data-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #eaecf0;
            text-align: left;
            vertical-align: top;
        }
        .data-table th {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #667085;
            background: #f8fafc;
        }
        .data-table td.amount,
        .data-table th.amount {
            text-align: right;
            font-weight: 800;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }
        .badge-blue { background: #dbeafe; color: #1d4ed8; }
        .badge-emerald { background: #dcfce7; color: #067647; }
        .badge-amber { background: #fef3c7; color: #b54708; }
        .empty-state {
            padding: 18px;
            border-radius: 16px;
            border: 1px dashed #d0d5dd;
            background: #f8fafc;
            color: #667085;
        }
        .mini-stat-list {
            display: grid;
            gap: 12px;
        }
        .mini-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            padding: 14px 16px;
            border-radius: 14px;
            background: #f8fafc;
            border: 1px solid #eaecf0;
        }
        .mini-stat .name {
            font-weight: 700;
            color: #344054;
        }
        .mini-stat .amount {
            font-weight: 800;
            font-size: 18px;
            text-align: right;
        }
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .status-pill.ok {
            background: #dcfce7;
            color: #067647;
        }
        .status-pill.warn {
            background: #fef3c7;
            color: #b54708;
        }
        .statement-grid {
            display: grid;
            gap: 16px;
        }
        .statement-block {
            border: 1px solid #eaecf0;
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
        }
        .statement-block + .statement-block {
            margin-top: 18px;
        }
        .statement-block-header {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 16px 18px;
            background: linear-gradient(180deg, #fcfdff, #f8fafc);
            border-bottom: 1px solid #eaecf0;
        }
        .statement-block-header h3 {
            margin: 0;
            font-size: 18px;
        }
        .statement-block-header p {
            margin: 4px 0 0;
            color: #667085;
            font-size: 13px;
        }
        .statement-total {
            font-weight: 800;
            font-size: 16px;
            text-align: right;
        }
        .statement-row-total td {
            background: #f8fafc;
            font-weight: 800;
        }
        .statement-row-grand td {
            background: #e0ecff;
            color: #12336b;
            font-weight: 800;
            font-size: 15px;
        }
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 20px;
        }
        .balance-list {
            display: grid;
            gap: 12px;
        }
        .balance-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            padding: 12px 0;
            border-bottom: 1px solid #eaecf0;
        }
        .balance-item:last-child {
            border-bottom: none;
        }
        .balance-item .meta {
            color: #667085;
            font-size: 13px;
            margin-top: 4px;
        }
        .balance-item .amount {
            font-weight: 800;
            text-align: right;
        }
        .balance-total {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding-top: 16px;
            margin-top: 8px;
            border-top: 2px solid #d0d5dd;
            font-weight: 800;
        }
        .journal-entry {
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            overflow: hidden;
            background: #fff;
            margin-bottom: 16px;
        }
        .journal-head {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 18px;
            background: linear-gradient(180deg, #fcfdff, #f8fafc);
            border-bottom: 1px solid #eaecf0;
        }
        .journal-head h3 {
            margin: 0 0 8px;
            font-size: 19px;
        }
        .journal-head p {
            margin: 0;
            color: #667085;
            font-size: 14px;
        }
        .journal-meta {
            display: grid;
            gap: 6px;
            text-align: right;
        }
        .account-card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 14px;
        }
        .account-card {
            padding: 16px;
            border-radius: 16px;
            border: 1px solid #eaecf0;
            background: #fff;
        }
        .account-code {
            color: #155eef;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.06em;
        }
        .account-name {
            margin-top: 10px;
            font-size: 18px;
            font-weight: 800;
        }
        .account-balance {
            margin-top: 12px;
            font-size: 22px;
            font-weight: 800;
        }
        .account-note {
            margin-top: 6px;
            color: #667085;
            font-size: 13px;
        }
        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }
        @media (max-width: 1100px) {
            .two-up {
                grid-template-columns: 1fr;
            }
            .balance-grid {
                grid-template-columns: 1fr;
            }
        }
        @media (max-width: 760px) {
            .topbar {
                flex-direction: column;
            }
            .filters {
                align-items: stretch;
            }
            .filter-form {
                width: 100%;
            }
            .tabbar-actions {
                width: 100%;
            }
            .filter-form input,
            .filter-form select,
            .filter-form .btn {
                width: 100%;
            }
            .journal-head {
                flex-direction: column;
            }
            .journal-meta {
                text-align: left;
            }
        }
    </style>
    @stack('styles')
</head>
<body>
    @include('layouts.sidebar', ['clientName' => $clientName, 'branchName' => $branchName])

    <main class="content" id="mainContent">
        @yield('content')
    </main>
</body>
</html>
