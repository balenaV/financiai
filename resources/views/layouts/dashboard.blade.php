<!DOCTYPE html>
<html lang="pt-BR" data-theme="{{ auth()->user()->settings()->firstOrCreate()->theme }}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Painel' }} — financiaí</title>
<link rel="icon" type="image/png" href="{{ asset('design/assets/capi/capi-rosto.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.2/css/all.min.css" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('design/css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/base.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/dashboard.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/mfa.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/fase5.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/app-additions.css') }}">
<script>
    try { localStorage.setItem('financiai:theme', document.documentElement.getAttribute('data-theme')); } catch (e) {}
</script>
</head>
<body>

{{ $slot }}

<div id="toast-container" class="toast-stack" aria-live="polite">
    @foreach(['success', 'error'] as $kind)
        @if(session($kind))
            <div class="toast toast--{{ $kind }}" role="status">
                <i class="fa-solid {{ $kind === 'success' ? 'fa-circle-check' : 'fa-circle-exclamation' }}" aria-hidden="true"></i>
                <span>{{ session($kind) }}</span>
            </div>
        @endif
    @endforeach
</div>

<script src="{{ asset('design/js/theme.js') }}"></script>
<script src="{{ asset('design/js/dashboard.js') }}"></script>
<script src="{{ asset('design/js/dashboard-settings-sync.js') }}"></script>
<script src="{{ asset('design/js/form-widgets-sync.js') }}"></script>
<script src="{{ asset('design/js/mfa.js') }}"></script>
<script src="{{ asset('design/js/mfa-reauth-sync.js') }}"></script>
<script>
    setTimeout(() => {
        document.querySelectorAll('#toast-container .toast').forEach((el) => {
            el.classList.add('is-leaving');
            setTimeout(() => el.remove(), 250);
        });
    }, 4500);
</script>
</body>
</html>
