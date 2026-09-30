<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #f5f7fb; color: #162638; }
        .layout { display: flex; min-height: 100vh; }
        .content { flex: 1; width: 100%; max-width: 100%; margin-left: 260px; padding: 20px; transition: margin-left .3s ease; }
        .content.expanded { margin-left: 80px; }
        .topbar, .panel { background: #fff; border: 1px solid #e2e8ee; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
        .page-head, .actions, .form-actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        h1 { font-size: 24px; margin: 0; }
        h2 { font-size: 18px; margin: 0 0 14px; }
        .muted { color: #607184; font-size: 13px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; gap: 7px; min-height: 36px; padding: 7px 12px; border: 1px solid #d2dce5; border-radius: 6px; background: #fff; color: #15354a; font: inherit; font-size: 13px; font-weight: 700; text-decoration: none; cursor: pointer; }
        .btn:hover { background: #f0f5f7; }
        .btn-primary { background: #17774b; border-color: #17774b; color: #fff; }
        .btn-primary:hover { background: #11603c; }
        .btn-danger { color: #b42318; border-color: #e7b4ad; }
        .btn-danger:hover { background: #fff1ef; }
        .btn:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible { outline: 3px solid #75b7df; outline-offset: 2px; }
        .form-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
        .field.wide { grid-column: 1 / -1; }
        label { font-size: 12px; font-weight: 700; color: #324c5d; }
        input, select, textarea { width: 100%; min-height: 36px; padding: 7px 9px; border: 1px solid #ccd7df; border-radius: 5px; background: #fff; color: #182d3e; font: inherit; font-size: 13px; }
        textarea { min-height: 72px; resize: vertical; }
        .table-wrap { overflow-x: auto; border: 1px solid #dce5ea; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 9px 10px; text-align: left; border-bottom: 1px solid #e3e9ee; vertical-align: middle; }
        th { background: #f3f7f8; color: #41596c; font-size: 11px; text-transform: uppercase; }
        tr:last-child td { border-bottom: 0; }
        .numeric { text-align: right; white-space: nowrap; }
        .line-table { min-width: 850px; }
        .line-table input, .line-table select { min-width: 0; }
        .line-table .product-select { min-width: 190px; }
        .line-table .description-input { min-width: 170px; }
        .line-table .quantity-input, .line-table .price-input { width: 105px; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: end; }
        .filters .field { min-width: 190px; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
        .badge-draft { background: #e9f0fa; color: #255384; }
        .badge-issued { background: #e2f5e9; color: #18603c; }
        .badge-cancelled { background: #fdebe8; color: #a1261b; }
        .notice { padding: 11px 13px; margin-bottom: 14px; border-radius: 5px; border: 1px solid #bddfca; background: #eaf7ed; color: #1d633b; }
        .notice-error { border-color: #efb8b0; background: #fff0ed; color: #a4261c; }
        .notice-error ul { margin: 6px 0 0; padding-left: 20px; }
        .detail-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 15px; }
        .detail-grid dt { font-size: 11px; color: #63778b; text-transform: uppercase; margin-bottom: 4px; }
        .detail-grid dd { margin: 0; font-size: 14px; font-weight: 600; overflow-wrap: anywhere; }
        .summary { margin-left: auto; width: min(320px, 100%); }
        .summary div { display: flex; justify-content: space-between; gap: 20px; padding: 5px 0; }
        .summary .grand { border-top: 2px solid #98aeb9; margin-top: 6px; padding-top: 11px; font-weight: 800; }
        .danger-zone { border-top: 1px solid #e4eaed; margin-top: 22px; padding-top: 18px; }
        @media (max-width: 900px) { .form-grid, .detail-grid { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 680px) { .content, .content.expanded { margin-left: 0; padding: 12px; } .form-grid, .detail-grid { grid-template-columns: 1fr; } .field.wide { grid-column: auto; } }
    </style>
</head>
<body>
<div class="layout">
    @include('layouts.sidebar')
    <main class="content" id="mainContent">
        <div class="topbar page-head">
            <div><h1>@yield('page-title')</h1><div class="muted">{{ $user->client?->name }} / {{ $user->branch?->name }}</div></div>
            @yield('top-actions')
        </div>
        @if(session('success')) <div class="notice" role="status">{{ session('success') }}</div> @endif
        @if($errors->any())
            <div class="notice notice-error" role="alert">
                Please correct the order details.
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
