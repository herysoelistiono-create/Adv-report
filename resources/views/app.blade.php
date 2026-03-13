<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- PWA -->
  <meta name="theme-color" content="#388E3C">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="default">
  <meta name="apple-mobile-web-app-title" content="AdvReport">
  <link rel="manifest" href="/build/manifest.webmanifest">
  <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

  <title inertia>{{ config('app.name', 'Laravel') }}</title>

  <link type="image/png" href="/assets/img/favicon.png" rel="icon" />
  <!-- Fonts -->
  <link href="https://fonts.bunny.net" rel="preconnect">
  <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined" rel="stylesheet">

  <!-- Scripts -->
  <script>
    window.CONFIG = {}
    window.CONFIG.LOCALE = "{{ app()->getLocale() }}";
    window.CONFIG.APP_NAME = "{{ config('app.name', 'Laravel') }}";
    window.CONFIG.APP_VERSION = {{ config('app.version', 0x010000) }};
    window.CONFIG.APP_VERSION_STR = "{{ config('app.version_str', '1.0.0') }}";
    window.CONSTANTS = <?= json_encode([
          'USER_ROLES' => \App\Models\User::Roles,
          'DEMO_PLOT_PLANT_STATUSES' => \App\Models\DemoPlot::PlantStatuses,
      ]) ?>;
    window.CONSTANTS.USER_ROLE_ADMIN = "{{ \App\Models\User::Role_Admin }}";
    window.CONSTANTS.USER_ROLE_ASM = "{{ \App\Models\User::Role_ASM }}";
    window.CONSTANTS.USER_ROLE_AGRONOMIST = "{{ \App\Models\User::Role_Agronomist }}";
    window.CONSTANTS.USER_ROLE_BS = "{{ \App\Models\User::Role_BS }}";
    @if (!!env('APP_DEMO'))
      window.CONFIG.APP_DEMO = 1
    @endif
  </script>
  @routes
  @vite(['resources/js/app.js', 'resources/css/app.css'])

  @inertiaHead
</head>

<body class="font-sans antialiased">
  @inertia
  <script>
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function () {
        navigator.serviceWorker.register('/sw.js', { scope: '/' });
      });
    }
  </script>
</body>

</html>
