<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h2 class="text-2xl font-bold text-white">Varlıklar</h2>
        <a href="{{ route('dashboard') }}" class="text-sm text-slate-400 hover:text-slate-200 underline underline-offset-4">
            &larr; Dashboard
        </a>
    </div>

    <div class="flex gap-2 border-b border-slate-800">
        @foreach (['solar' => 'Güneş', 'wind' => 'Rüzgâr', 'battery' => 'Batarya', 'consumption' => 'Tüketim'] as $key => $label)
            <button
                type="button"
                wire:click="setTab('{{ $key }}')"
                class="px-4 py-2 text-sm font-semibold border-b-2 -mb-px transition-colors
                    {{ $tab === $key ? 'border-brand-green text-brand-green' : 'border-transparent text-slate-400 hover:text-slate-200' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Form --}}
        <form wire:submit="save" class="lg:col-span-1 space-y-4 bg-brand-navy border border-brand-navy-light rounded-[10px] p-5">
            <h3 class="text-sm font-semibold text-slate-300">
                {{ $editingId ? 'Kaydı Düzenle' : 'Yeni Kayıt' }}
            </h3>

            <div>
                <label class="block text-xs text-slate-400 mb-1">Ad</label>
                <input type="text" wire:model="name"
                    class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                @error('name') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
            </div>

            @if ($tab === 'solar' || $tab === 'wind')
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Kapasite (kW)</label>
                    <input type="number" step="0.01" wire:model="capacityKw"
                        class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                    @error('capacityKw') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($tab === 'consumption')
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Ortalama Tüketim (kWh)</label>
                    <input type="number" step="0.01" wire:model="averageDemandKwh"
                        class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                    @error('averageDemandKwh') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            @if ($tab === 'battery')
                <div>
                    <label class="block text-xs text-slate-400 mb-1">Kapasite (kWh)</label>
                    <input type="number" step="0.01" wire:model="capacityKwh"
                        class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                    @error('capacityKwh') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">SOC (%)</label>
                    <input type="number" step="0.01" wire:model="socPercent"
                        class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                    @error('socPercent') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Min SOC (%)</label>
                        <input type="number" step="0.01" wire:model="minSoc"
                            class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                        @error('minSoc') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="block text-xs text-slate-400 mb-1">Max SOC (%)</label>
                        <input type="number" step="0.01" wire:model="maxSoc"
                            class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                        @error('maxSoc') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Verimlilik (0-1)</label>
                    <input type="number" step="0.01" wire:model="efficiencyRate"
                        class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                    @error('efficiencyRate') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block text-xs text-slate-400 mb-1">Değiştirme Maliyeti (TL)</label>
                    <input type="number" step="0.01" wire:model="replacementCostTl"
                        class="w-full bg-slate-950 border border-slate-700 rounded-[10px] px-3 py-2 text-sm focus:outline-none focus:border-brand-green">
                    <p class="mt-1 text-[11px] text-slate-500">Bataryayı sıfırdan değiştirmenin maliyeti — depolama kararlarında yıpranma maliyetini hesaplamak için kullanılır.</p>
                    @error('replacementCostTl') <p class="text-xs text-rose-400 mt-1">{{ $message }}</p> @enderror
                </div>
            @endif

            <div class="flex gap-2 pt-2">
                <button type="submit" class="px-4 py-2 text-sm font-bold rounded-[10px] bg-brand-green text-slate-950 hover:brightness-90">
                    Kaydet
                </button>
                @if ($editingId)
                    <button type="button" wire:click="cancelEdit" class="px-4 py-2 text-sm font-semibold rounded-[10px] border border-slate-700 text-slate-300 hover:bg-slate-800">
                        Vazgeç
                    </button>
                @endif
            </div>
        </form>

        {{-- List --}}
        <div class="lg:col-span-2 bg-brand-navy border border-brand-navy-light rounded-[10px] p-5">
            @if ($tab === 'battery')
                @if ($battery)
                    <dl class="grid grid-cols-2 gap-4 text-sm">
                        <div><dt class="text-slate-400">Ad</dt><dd>{{ $battery->getName() }}</dd></div>
                        <div><dt class="text-slate-400">Kapasite</dt><dd>{{ $battery->getCapacityKwh() }} kWh</dd></div>
                        <div><dt class="text-slate-400">SOC</dt><dd>%{{ $battery->getSocPercent() }}</dd></div>
                        <div><dt class="text-slate-400">Min / Max SOC</dt><dd>%{{ $battery->getMinSoc() }} / %{{ $battery->getMaxSoc() }}</dd></div>
                        <div><dt class="text-slate-400">Verimlilik</dt><dd>{{ $battery->getEfficiencyRate() }}</dd></div>
                        <div><dt class="text-slate-400">Değiştirme Maliyeti</dt><dd>{{ number_format($battery->getReplacementCostTl(), 2) }} TL</dd></div>
                    </dl>
                    <button wire:click="deleteBattery" wire:confirm="Bataryayı silmek istediğine emin misin?"
                        class="mt-4 px-3 py-1.5 text-xs font-semibold rounded-[10px] border border-rose-800 text-rose-400 hover:bg-rose-950">
                        Sil
                    </button>
                @else
                    <p class="text-sm text-slate-400">Henüz batarya tanımlanmadı.</p>
                @endif
            @else
                @if ($records->isEmpty())
                    <p class="text-sm text-slate-400">Kayıt yok.</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-800">
                                <th class="py-2 font-medium">Ad</th>
                                <th class="py-2 font-medium">
                                    {{ match($tab) { 'consumption' => 'Ort. Tüketim (kWh)', default => 'Kapasite (kW)' } }}
                                </th>
                                <th class="py-2 font-medium text-right">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($records as $record)
                                <tr class="border-b border-slate-800/60">
                                    <td class="py-2">{{ $record->getName() }}</td>
                                    <td class="py-2">
                                        {{ $tab === 'consumption' ? $record->getAverageDemandKwh() : $record->getCapacityKw() }}
                                    </td>
                                    <td class="py-2 text-right space-x-2">
                                        <button wire:click="edit('{{ $record->getId() }}')" class="text-xs text-brand-green hover:underline">
                                            Düzenle
                                        </button>
                                        <button wire:click="delete('{{ $record->getId() }}')" wire:confirm="Silmek istediğine emin misin?" class="text-xs text-rose-400 hover:underline">
                                            Sil
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            @endif
        </div>
    </div>
</div>
