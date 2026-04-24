@extends('prints.layout')

@php
    $pageTitle = 'Journals';
    $pageBadge = 'Accounting';
    $rangeLabel = $from->format('d M Y') . ' to ' . $to->format('d M Y');
@endphp

@section('content')
    @foreach($entries as $entry)
        <div class="section">
            <h3>{{ $entry['reference_number'] }}</h3>
            <p>{{ $entry['display_date'] }} | {{ $entry['source_label'] }} | {{ $entry['party'] }} | {{ $entry['entered_by'] }}</p>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Account Code</th>
                            <th>Account Name</th>
                            <th class="amount">Debit</th>
                            <th class="amount">Credit</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entry['lines'] as $line)
                            <tr>
                                <td>{{ $line['account_code'] }}</td>
                                <td>{{ $line['account_name'] }}</td>
                                <td class="amount">{{ number_format((float) $line['debit'], 2) }}</td>
                                <td class="amount">{{ number_format((float) $line['credit'], 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endforeach
@endsection
