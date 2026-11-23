<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TVU Canal 11 · Gestión Publicitaria</title>
    <link rel="icon" type="image/png" href="{{ asset('img/logo-tvu.png') }}">
    <meta name="theme-color" content="#0a35a8">
    <meta name="description" content="Sistema de gestión publicitaria de Canal 11 / UAGRM">
    {{-- Rutas base para React (permite desplegar bajo /inf513/grupo13sa/proyecto2) --}}
    <meta name="app-base" content="{{ rtrim(parse_url(config('app.url'), PHP_URL_PATH) ?? '', '/') }}">
    <meta name="api-base" content="{{ rtrim(config('app.url'), '/') }}/api/v1">
    <script>
        // Aplica tema y accesibilidad antes de pintar (evita parpadeo).
        (function () {
            try {
                var p = JSON.parse(localStorage.getItem('preferencias') || '{}');
                var h = new Date().getHours();
                var modo = p.modo && p.modo !== 'auto' ? p.modo : (h >= 6 && h < 19 ? 'dia' : 'noche');
                var r = document.documentElement;
                r.dataset.tema = p.tema || 'adultos';
                r.dataset.modo = modo;
                if (p.contraste) r.dataset.contraste = 'alto';
                if (p.fuente) r.style.fontSize = p.fuente + '%';
            } catch (e) {}
        })();
    </script>
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.jsx'])
</head>
<body>
    <div id="app"></div>
    <noscript>Este sistema necesita JavaScript habilitado.</noscript>
</body>
</html>
