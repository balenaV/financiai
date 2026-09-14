<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>{{ $title ?? 'Entrar' }} — financiaí</title>
<link rel="icon" type="image/png" href="{{ asset('design/assets/capi/capi-rosto.png') }}">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Figtree:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="{{ asset('design/css/tokens.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/base.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/auth.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/mfa.css') }}">
<link rel="stylesheet" href="{{ asset('design/css/app-additions.css') }}">
<script>
    (function () {
        try {
            var theme = localStorage.getItem('financiai:theme');
            if (theme === 'dark' || theme === 'light') {
                document.documentElement.setAttribute('data-theme', theme);
            }
        } catch (e) {}
    })();
</script>
</head>
<body>

{{ $slot }}

<script src="{{ asset('design/js/auth.js') }}"></script>
<script src="{{ asset('design/js/auth-url-sync.js') }}"></script>
<script src="{{ asset('design/js/mfa-challenge-sync.js') }}"></script>
</body>
</html>
