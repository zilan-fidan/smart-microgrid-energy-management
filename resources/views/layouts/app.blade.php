<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Smart Microgrid Energy Management' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
</head>
<body class="min-h-screen bg-slate-950 text-slate-300 antialiased">
    <div class="min-h-screen flex flex-col">
        <header class="w-full bg-brand-navy border-b border-brand-navy-light">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between">
                <h1 class="text-lg font-semibold tracking-tight text-white">Smart Microgrid Energy Management</h1>
                <span class="text-xs text-slate-400">Karar destek sistemi</span>
            </div>
        </header>

        <main class="flex-1 w-full">
            <div class="mx-auto w-full max-w-7xl px-6 py-8">
                {{ $slot }}
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
