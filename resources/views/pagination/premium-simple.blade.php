@once
    <style>
        .premium-pagination-shell {
            margin-top: 18px;
            padding: 14px 16px;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            background: linear-gradient(180deg, #ffffff, #f8fbff);
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .premium-pagination-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .premium-page-link,
        .premium-page-disabled {
            min-width: 42px;
            height: 42px;
            padding: 0 16px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
        }

        .premium-page-link {
            color: #0f172a;
            background: #ffffff;
            border: 1px solid #dbe2ea;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .premium-page-disabled {
            color: #98a2b3;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            cursor: not-allowed;
        }
    </style>
@endonce

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="premium-pagination-shell">
        <div class="premium-pagination-nav">
            @if ($paginator->onFirstPage())
                <span class="premium-page-disabled" aria-disabled="true">Previous</span>
            @else
                <a class="premium-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @if ($paginator->hasMorePages())
                <a class="premium-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="premium-page-disabled" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
