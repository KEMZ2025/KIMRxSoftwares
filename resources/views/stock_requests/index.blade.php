<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KIM Rx</title>
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
</head>
<body class="sr-page">
@include('layouts.sidebar')
@include('stock_requests._assets')
<main class="content sr-content" id="mainContent" data-request-book>
    <header class="sr-heading sr-page-heading">
        <div><h1>Stock Requests</h1><p class="sr-muted">{{ $clientName }} | {{ $branchName }}</p></div>
        @if($canRecord)
            <button type="button" class="sr-button sr-primary" data-stock-request-open>
                <img src="{{ asset('vendor/lucide-stock-requests/plus.svg') }}" width="18" height="18" alt=""> Record Request
            </button>
        @endif
    </header>
    <nav class="sr-tabs" aria-label="Stock request views">
        <a href="{{ route('stock-requests.index', array_merge(request()->except('page', 'key', 'status'), ['view' => 'requests'])) }}" @if(!$procurement) aria-current="page" @endif>Requests</a>
        <a href="{{ route('stock-requests.index', array_merge(request()->except('page', 'key', 'status'), ['view' => 'procurement'])) }}" @if($procurement) aria-current="page" @endif>To Order</a>
    </nav>
    <div class="sr-counts">
        @foreach($statusLabels as $status => $label)
            <span><span class="sr-dot sr-{{ $status }}"></span>{{ $label }} <strong>{{ number_format($counts[$status] ?? 0) }}</strong></span>
        @endforeach
    </div>
    <form method="GET" action="{{ route('stock-requests.index') }}" class="sr-filters">
        <input type="hidden" name="view" value="{{ $procurement ? 'procurement' : 'requests' }}">
        @if($filters['key'] ?? null)<input type="hidden" name="key" value="{{ $filters['key'] }}">@endif
        <div class="sr-filter-search"><label for="sr-filter-search">Medicine</label><input id="sr-filter-search" class="sr-input" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search requests..." maxlength="255"></div>
        <div><label for="sr-filter-status">Status</label><select id="sr-filter-status" class="sr-input" name="status"><option value="">All</option>@foreach($statusLabels as $status => $label)@if(!$procurement || in_array($status, ['pending', 'ordered']))<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $label }}</option>@endif @endforeach</select></div>
        <div><label for="sr-from">From</label><input type="date" class="sr-input" id="sr-from" name="from" value="{{ $filters['from'] ?? '' }}"></div>
        <div><label for="sr-to">To</label><input type="date" class="sr-input" id="sr-to" name="to" value="{{ $filters['to'] ?? '' }}"></div>
        <button class="sr-icon-button" title="Apply filters" aria-label="Apply filters"><img src="{{ asset('vendor/lucide-stock-requests/search.svg') }}" width="20" height="20" alt=""></button>
        <a class="sr-text-link" href="{{ route('stock-requests.index', ['view' => $procurement ? 'procurement' : 'requests']) }}">Reset</a>
    </form>
    @if($errors->any())<div class="sr-error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="sr-table-wrap">
        <table class="sr-table">
            <thead><tr><th>Medicine</th>@if($procurement)<th>Requests</th><th>Quantity</th><th>Last Requested</th>@else<th>Quantity</th><th>Status</th><th>Free Stock</th><th>Requested By</th><th>Date</th>@endif</tr></thead>
            <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>
                        <a class="sr-medicine" href="{{ $procurement ? route('stock-requests.index', ['key' => $row->request_key]) : route('stock-requests.show', $row->id) }}">{{ $row->medicine_name }}</a>
                        <div class="sr-muted">{{ implode(' | ', array_filter([$row->strength, $row->dosage_form])) }}</div>
                        @if(!$row->product_id)<span class="sr-unlisted">Unlisted</span>@endif
                    </td>
                    @if($procurement)<td>{{ number_format($row->request_count) }}</td>@endif
                    <td class="sr-quantity">{{ $row->quantity !== null ? rtrim(rtrim(number_format((float) $row->quantity, 2), '0'), '.') : '-' }} {{ $row->unit_name }}
                        @if($procurement && $row->unspecified_count > 0)<div class="sr-muted">{{ $row->unspecified_count }} unspecified</div>@endif
                    </td>
                    @if($procurement)
                        <td>{{ \Illuminate\Support\Carbon::parse($row->last_requested)->format('d M Y H:i') }}</td>
                    @else
                        <td><span class="sr-status sr-{{ $row->display_status }}">{{ $statusLabels[$row->display_status] }}</span></td>
                        <td>{{ $row->product_id ? rtrim(rtrim(number_format((float) $row->free_stock, 2), '0'), '.') : '-' }}</td>
                        <td>{{ $row->creator?->name ?? 'Former staff' }}</td>
                        <td>{{ $row->created_at->format('d M Y H:i') }}</td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="{{ $procurement ? 4 : 6 }}" class="sr-empty">{{ $procurement ? 'No medicines awaiting stock.' : 'No requests found.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="sr-pagination">{{ $rows->links() }}</div>
</main>
@include('stock_requests._modal')
</body>
</html>
