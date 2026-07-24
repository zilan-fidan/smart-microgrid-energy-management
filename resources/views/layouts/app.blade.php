<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Smart Microgrid Energy Management' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 antialiased">
    <div class="min-h-screen flex flex-col">
        <header class="border-b border-slate-800 bg-slate-900/60">
            <div class="mx-auto max-w-7xl px-6 py-4 flex items-center justify-between">
                <h1 class="text-lg font-semibold tracking-tight">Smart Microgrid Energy Management</h1>
                <span class="text-xs text-slate-400">Karar destek sistemi</span>
            </div>
        </header>

        <main class="flex-1 mx-auto w-full max-w-7xl px-6 py-8">
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
