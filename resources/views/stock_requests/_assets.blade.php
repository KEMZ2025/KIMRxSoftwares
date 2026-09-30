@once
@unless($stockRequestStylesInHead ?? false)
<link rel="stylesheet" href="{{ asset('css/stock-requests.css') }}?v=1">
@endunless
<script src="{{ asset('js/stock-requests.js') }}?v=1" defer></script>
@endonce
