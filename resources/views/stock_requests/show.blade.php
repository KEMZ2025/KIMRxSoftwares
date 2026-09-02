<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>KIM Rx</title><link rel="icon" type="image/png" href="{{ asset('favicon.png') }}"></head>
<body class="sr-page">
@include('layouts.sidebar')
@include('stock_requests._assets')
<main class="content sr-content" id="mainContent">
    <a href="{{ route('stock-requests.index') }}" class="sr-back"><img src="{{ asset('vendor/lucide-stock-requests/arrow-left.svg') }}" width="18" height="18" alt="">Stock Requests</a>
    <header class="sr-heading sr-page-heading">
        <div><h1>{{ $entry->medicine_name }}</h1><p class="sr-muted">{{ implode(' | ', array_filter([$entry->strength, $entry->dosage_form])) }}</p></div>
        <span class="sr-status sr-{{ $entry->display_status }}">{{ $statusLabels[$entry->display_status] }}</span>
    </header>
    @if(session('success'))<div class="sr-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="sr-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <dl class="sr-details">
        <div><dt>Quantity Requested</dt><dd>{{ $entry->quantity ?? '-' }} {{ $entry->unit_name }}</dd></div>
        <div><dt>Free Stock</dt><dd>{{ $entry->product_id ? number_format((float) $entry->free_stock, 2) : '-' }} {{ $entry->product?->unit?->name }}</dd></div>
        <div><dt>Requested By</dt><dd>{{ $entry->creator?->name ?? 'Former staff' }}</dd></div>
        <div><dt>Date</dt><dd>{{ $entry->created_at->format('d M Y H:i') }}</dd></div>
        <div><dt>Branch</dt><dd>{{ $branchName }}</dd></div>
        <div><dt>Linked Medicine</dt><dd>{{ $entry->product?->name ?? 'Unlisted' }}</dd></div>
    </dl>
    @if($entry->note)<p class="sr-note">{{ $entry->note }}</p>@endif
    @if($canManage)
        <section class="sr-section">
            <h2>Follow-up</h2>
            <form method="POST" action="{{ route('stock-requests.update', $entry->id) }}" class="sr-manage-form">
                @csrf @method('PUT')
                <input type="hidden" name="version" value="{{ $entry->version }}">
                <div class="sr-manage-grid">
                    <div><label for="sr-follow-up">Follow-up</label><select class="sr-input" name="status" id="sr-follow-up">@foreach(['pending', 'ordered', 'closed'] as $status)<option value="{{ $status }}" @selected(old('status', $entry->status) === $status)>{{ $statusLabels[$status] }}</option>@endforeach</select></div>
                    @include('stock_requests._picker', ['pickerId' => 'sr-link-product', 'pickerLabel' => 'Linked Medicine', 'pickerProduct' => $entry->product])
                </div>
                <label for="sr-follow-note">Note <span class="sr-muted">(optional)</span></label>
                <textarea class="sr-input" rows="2" id="sr-follow-note" name="note" maxlength="1000">{{ old('note') }}</textarea>
                <button class="sr-button sr-primary">Save Changes</button>
            </form>
        </section>
    @endif
    <section class="sr-section">
        <h2>History</h2>
        <div class="sr-table-wrap"><table class="sr-table"><thead><tr><th>Date</th><th>Staff</th><th>Action</th><th>Follow-up</th><th>Note</th></tr></thead><tbody>
        @foreach($history as $event)
            <tr><td>{{ $event->created_at->format('d M Y H:i') }}</td><td>{{ $event->user?->name ?? 'Former staff' }}</td><td>{{ $event->action }}@if(($event->old_values['product_id'] ?? null) !== ($event->new_values['product_id'] ?? null) && $event->action === 'Updated')<div class="sr-muted">Medicine linked</div>@endif</td><td>{{ $statusLabels[$event->new_values['status'] ?? 'pending'] }}</td><td>{{ $event->reason ?: ($event->new_values['note'] ?? '-') }}</td></tr>
        @endforeach
        </tbody></table></div>
        <div class="sr-pagination">{{ $history->links() }}</div>
    </section>
</main>
</body>
</html>
