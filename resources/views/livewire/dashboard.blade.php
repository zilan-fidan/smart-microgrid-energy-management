<div class="space-y-6">
    <h2 class="text-2xl font-bold text-white">Dashboard</h2>
    <p class="text-slate-400">Güneş, rüzgar, batarya ve tüketim varlıklarını yönet; 24 saatlik karar motorunu çalıştır.</p>

    {{-- Summary strip --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
            <p class="text-xs text-slate-400">Batarya SOC</p>
            @if ($batterySocPercent !== null)
                <p class="mt-1 text-2xl font-bold text-brand-green">%{{ round($batterySocPercent, 1) }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">Batarya eklenmedi</p>
            @endif
        </div>

        <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
            <p class="text-xs text-slate-400">Kayıtlı Varlık</p>
            <p class="mt-1 text-2xl font-bold text-white">{{ $solarCount + $windCount + $consumptionCount }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $solarCount }} güneş, {{ $windCount }} rüzgar, {{ $consumptionCount }} tüketim</p>
        </div>

        <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
            <p class="text-xs text-slate-400">Son Simülasyon</p>
            @if ($lastSimulationAt)
                <p class="mt-1 text-sm font-semibold text-white">{{ $lastSimulationAt->diffForHumans() }}</p>
            @else
                <p class="mt-1 text-sm text-slate-500">Henüz çalıştırılmadı</p>
            @endif
        </div>

        <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
            <p class="text-xs text-slate-400">Son Tasarruf</p>
            @if ($lastSimulationSavingsTl !== null)
                <p class="mt-1 text-2xl font-bold text-brand-green">{{ number_format($lastSimulationSavingsTl, 2) }} TL</p>
            @else
                <p class="mt-1 text-sm text-slate-500">Henüz çalıştırılmadı</p>
            @endif
        </div>
    </div>

    {{-- Navigation cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <a href="{{ route('assets') }}"
            class="flex items-start gap-4 rounded-[10px] p-6 bg-brand-navy border border-brand-navy-light hover:border-brand-green transition-colors">
            <svg class="w-8 h-8 shrink-0 text-brand-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 2 3 14h7l-1 8 10-12h-7l1-8Z" />
            </svg>
            <div>
                <p class="text-base font-semibold text-white">Varlıkları Yönet</p>
                <p class="mt-1 text-sm text-slate-400">Güneş, rüzgar, batarya ve tüketim noktalarını ekle/düzenle.</p>
                <span class="mt-3 inline-block text-sm text-brand-green">Aç &rarr;</span>
            </div>
        </a>
        <a href="{{ route('simulation') }}"
            class="flex items-start gap-4 rounded-[10px] p-6 bg-brand-navy border border-brand-navy-light hover:border-brand-green transition-colors">
            <svg class="w-8 h-8 shrink-0 text-brand-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M5 3v18l15-9L5 3Z" />
            </svg>
            <div>
                <p class="text-base font-semibold text-white">Simülasyonu Çalıştır</p>
                <p class="mt-1 text-sm text-slate-400">24 saatlik karar motorunu çalıştır, SOC/maliyet/tasarruf sonuçlarını gör.</p>
                <span class="mt-3 inline-block text-sm text-brand-green">Aç &rarr;</span>
            </div>
        </a>
    </div>

    {{-- Last simulation summary --}}
    @if ($lastSimulationHourly)
        <div class="space-y-4" wire:ignore>
            <h3 class="text-lg font-semibold text-white">Son Simülasyon Özeti</h3>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-sm text-slate-300 mb-2">SOC (saatlik)</p>
                    <canvas id="dashboardSocChart" height="200"></canvas>
                </div>
                <div class="rounded-[10px] p-4 bg-brand-navy border border-brand-navy-light">
                    <p class="text-sm text-slate-300 mb-2">Karar Dağılımı</p>
                    <canvas id="dashboardDecisionChart" height="200"></canvas>
                </div>
            </div>
        </div>

        @script
        <script>
            const hourly = @js($lastSimulationHourly);
            const actionCounts = @js($lastSimulationActionCounts);

            new Chart(document.getElementById('dashboardSocChart'), {
                type: 'line',
                data: {
                    labels: hourly.map(h => String(h.hour).padStart(2, '0') + ':00'),
                    datasets: [
                        {
                            label: 'SOC (%)',
                            data: hourly.map(h => h.soc),
                            borderColor: '#00E500',
                            backgroundColor: '#00E500',
                            tension: 0.3,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            min: 0,
                            max: 100,
                            ticks: { color: '#94a3b8' },
                            grid: { color: '#243256' },
                        },
                        x: {
                            ticks: { color: '#94a3b8' },
                            grid: { color: '#243256' },
                        },
                    },
                    plugins: {
                        legend: { labels: { color: '#e2e8f0' } },
                    },
                },
            });

            const decisionLabels = { store: 'Depola', sell: 'Sat', use_battery: 'Bataryayı Kullan', draw_from_grid: 'Şebekeden Çek' };
            const decisionColors = { store: '#00E500', sell: '#60a5fa', use_battery: '#f97316', draw_from_grid: '#64748b' };
            const decisionKeys = Object.keys(decisionLabels);

            new Chart(document.getElementById('dashboardDecisionChart'), {
                type: 'doughnut',
                data: {
                    labels: decisionKeys.map(k => decisionLabels[k]),
                    datasets: [
                        {
                            data: decisionKeys.map(k => actionCounts[k] ?? 0),
                            backgroundColor: decisionKeys.map(k => decisionColors[k]),
                        },
                    ],
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: '#e2e8f0' } },
                    },
                },
            });
        </script>
        @endscript
    @endif
</div>
