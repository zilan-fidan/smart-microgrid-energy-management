<div class="space-y-6">
    <h2 class="text-2xl font-bold text-white">Dashboard</h2>
    <p class="text-slate-400">Güneş, rüzgar, batarya ve tüketim varlıklarını yönet; 24 saatlik karar motorunu çalıştır.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <a href="{{ route('assets') }}"
            class="block rounded-[10px] p-5 bg-brand-navy border border-brand-navy-light hover:border-brand-green transition-colors">
            <p class="text-sm font-semibold text-white">Varlıkları Yönet</p>
            <p class="mt-1 text-xs text-slate-400">Güneş, rüzgar, batarya ve tüketim noktalarını ekle/düzenle.</p>
            <span class="mt-3 inline-block text-sm text-brand-green">Aç &rarr;</span>
        </a>
        <a href="{{ route('simulation') }}"
            class="block rounded-[10px] p-5 bg-brand-navy border border-brand-navy-light hover:border-brand-green transition-colors">
            <p class="text-sm font-semibold text-white">Simülasyonu Çalıştır</p>
            <p class="mt-1 text-xs text-slate-400">24 saatlik karar motorunu çalıştır, SOC/maliyet/tasarruf sonuçlarını gör.</p>
            <span class="mt-3 inline-block text-sm text-brand-green">Aç &rarr;</span>
        </a>
    </div>
</div>
