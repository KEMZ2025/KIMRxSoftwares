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

        .premium-pagination-meta {
            color: #667085;
            font-size: 13px;
            line-height: 1.5;
        }

        .premium-pagination-meta strong {
            color: #111827;
            font-size: 14px;
        }

        .premium-pagination-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .premium-page-link,
        .premium-page-current,
        .premium-page-disabled,
        .premium-page-gap {
            min-width: 42px;
            height: 42px;
            padding: 0 14px;
            border-radius: 14px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
        }

        .premium-page-link {
            color: #0f172a;
            background: #ffffff;
            border: 1px solid #dbe2ea;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .premium-page-link:hover {
            transform: translateY(-1px);
            background: #f3f7ff;
            border-color: #c7d2fe;
            box-shadow: 0 12px 24px rgba(37, 99, 235, 0.12);
        }

        .premium-page-current {
            color: #ffffff;
            background: linear-gradient(135deg, #1f7a4f, #2563eb);
            border: 1px solid transparent;
            box-shadow: 0 10px 24px rgba(37, 99, 235, 0.18);
        }

        .premium-page-disabled {
            color: #98a2b3;
            background: #f8fafc;
            border: 1px solid #e5e7eb;
            cursor: not-allowed;
        }

        .premium-page-gap {
            min-width: auto;
            color: #98a2b3;
            background: transparent;
            border: none;
            padding: 0 4px;
            height: auto;
        }

        .premium-page-link.premium-page-prevnext,
        .premium-page-disabled.premium-page-prevnext {
            min-width: auto;
            padding: 0 16px;
        }

        @media (max-width: 720px) {
            .premium-pagination-shell {
                align-items: stretch;
            }

            .premium-pagination-nav {
                width: 100%;
            }
        }
    </style>
@endonce

@if ($paginator->hasPages())
    <nav role="navigation" aria-label="{{ __('Pagination Navigation') }}" class="premium-pagination-shell">
        <div class="premium-pagination-meta">
            <strong>Showing {{ $paginator->firstItem() }} to {{ $paginator->lastItem() }}</strong>
            of {{ $paginator->total() }} results
        </div>

        <div class="premium-pagination-nav">
            @if ($paginator->onFirstPage())
                <span class="premium-page-disabled premium-page-prevnext" aria-disabled="true">Previous</span>
            @else
                <a class="premium-page-link premium-page-prevnext" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="premium-page-gap" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="premium-page-current" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="premium-page-link" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="premium-page-link premium-page-prevnext" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="premium-page-disabled premium-page-prevnext" aria-disabled="true">Next</span>
            @endif
        </div>
    </nav>
@endif
