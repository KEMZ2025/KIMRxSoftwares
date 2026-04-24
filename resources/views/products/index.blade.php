<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Products - KIM Rx</title>
    <style>
        * { box-sizing: border-box; }

        :root {
            --sidebar-start: #1f7a4f;
            --sidebar-end: #6a1b9a;
            --sidebar-card: rgba(255,255,255,0.08);
            --sidebar-card-hover: rgba(255,255,255,0.16);
            --sidebar-card-active: rgba(255,255,255,0.22);
            --page-bg: #f4f6fb;
            --panel-bg: #ffffff;
            --text-main: #222;
            --text-soft: #666;
            --border-soft: #e7e7e7;
            --success: #18864b;
            --danger: #d32f2f;
            --edit: #6a1b9a;
            --info: #1565c0;
            --shadow-soft: 0 8px 24px rgba(0,0,0,0.06);
            --radius-lg: 18px;
            --radius-sm: 10px;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            min-height: 100vh;
            background: var(--page-bg);
            color: var(--text-main);
        }

        .layout {
            display: flex;
            min-height: 100vh;
        }

        .content {
            flex: 1;
            margin-left: 260px;
            padding: 24px;
            transition: margin-left 0.3s ease;
        }

        .content.expanded { margin-left: 80px; }

        .topbar {
            background: var(--panel-bg);
            border-radius: var(--radius-lg);
            padding: 18px 22px;
            margin-bottom: 22px;
            box-shadow: var(--shadow-soft);
        }

        .topbar h3 {
            margin: 0 0 8px;
            font-size: 18px;
        }

        .topbar p {
            margin: 0;
            color: var(--text-soft);
        }

        .panel {
            background: var(--panel-bg);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-soft);
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
            margin-bottom: 18px;
        }

        .panel-header h2 {
            margin: 0 0 6px;
            font-size: 20px;
        }

        .panel-header p {
            margin: 0;
            color: var(--text-soft);
        }

        .btn {
            display: inline-block;
            padding: 10px 14px;
            border-radius: var(--radius-sm);
            color: white;
            text-decoration: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }

        .btn-add { background: var(--success); }
        .btn-edit { background: var(--edit); }
        .btn-delete { background: var(--danger); }
        .btn-info { background: var(--info); }

        .alert-success {
            background: #e7f6ec;
            color: var(--success);
            padding: 12px 14px;
            border-radius: var(--radius-sm);
            margin-bottom: 16px;
            font-weight: 600;
        }

        .search-form { margin-bottom: 18px; }

        .search-form input {
            width: 100%;
            max-width: 360px;
            padding: 12px 14px;
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            outline: none;
            font-size: 14px;
            background: #fff;
        }

        .table-wrap { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        table th,
        table td {
            padding: 13px 12px;
            border-bottom: 1px solid var(--border-soft);
            text-align: left;
            font-size: 14px;
            vertical-align: middle;
        }

        table th {
            background: #fafafa;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            color: #444;
        }

        .status-active {
            color: var(--success);
            font-weight: 700;
        }

        .status-inactive {
            color: var(--danger);
            font-weight: 700;
        }

        .action-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            align-items: center;
        }

        .inline-form {
            display: inline;
            margin: 0;
        }

        .empty-row {
            text-align: center;
            color: var(--text-soft);
            padding: 20px 0;
        }

        .pagination-wrap {
            margin-top: 16px;
        }

        @media (max-width: 900px) {

            .content,
            .content.expanded {
                margin-left: 0;
            }

            .layout {
                flex-direction: column;
            }

            .topbar,
            .panel {
                border-radius: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="layout">
        @include('layouts.sidebar')

        <main class="content" id="mainContent">
            <div class="topbar">
                <h3>Welcome, {{ $user->name }}</h3>
                <p>Client: {{ $clientName }} | Branch: {{ $branchName }}</p>
            </div>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Products</h2>
                        <p>Manage all products in KIM Rx</p>
                    </div>

                    <a href="{{ route('products.create') }}" class="btn btn-add">Add Product</a>
                </div>

                @if(session('success'))
                    <div class="alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="GET" action="{{ route('products.index') }}" class="search-form">
                    <input
                        type="text"
                        name="search"
                        placeholder="Search by name, strength, barcode or description..."
                        value="{{ request('search') }}"
                    >
                </form>

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Strength</th>
                                <th>Barcode</th>
                                <th>Retail</th>
                                <th>Wholesale</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($products as $product)
                                <tr>
                                    <td>{{ $loop->iteration + ($products->currentPage() - 1) * $products->perPage() }}</td>
                                    <td>{{ $product->name }}</td>
                                    <td>{{ $product->strength ?? 'N/A' }}</td>
                                    <td>{{ $product->barcode ?? 'N/A' }}</td>
                                    <td>{{ number_format((float) $product->retail_price, 2) }}</td>
                                    <td>{{ number_format((float) $product->wholesale_price, 2) }}</td>
                                    <td>
                                        @if($product->is_active)
                                            <span class="status-active">Active</span>
                                        @else
                                            <span class="status-inactive">Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-cell">
                                            <a href="{{ route('products.sources', $product->id) }}" class="btn btn-info">View Sources</a>
                                            <a href="{{ route('products.edit', $product->id) }}" class="btn btn-edit">Edit</a>

                                            <form method="POST" action="{{ route('products.destroy', $product->id) }}" class="inline-form" onsubmit="return confirm('Are you sure you want to delete this product?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-delete">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="empty-row">No products found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="pagination-wrap">
                    {{ $products->withQueryString()->links() }}
                </div>
            </section>
        </main>
    </div>

</body>
</html>
