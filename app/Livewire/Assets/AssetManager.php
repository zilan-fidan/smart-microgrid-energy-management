<?php

namespace App\Livewire\Assets;

use App\Services\AssetService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

class AssetManager extends Component
{
    public string $tab = 'solar';

    public ?string $editingId = null;

    public string $name = '';

    public string $capacityKw = '';

    public string $averageDemandKwh = '';

    public string $capacityKwh = '';

    public string $socPercent = '';

    public string $minSoc = '';

    public string $maxSoc = '';

    public string $efficiencyRate = '';

    public string $replacementCostTl = '';

    protected AssetService $assetService;

    public function boot(AssetService $assetService): void
    {
        $this->assetService = $assetService;
    }

    public function mount(): void
    {
        $this->syncFormWithTab();
    }

    protected function rules(): array
    {
        return match ($this->tab) {
            'solar', 'wind' => [
                'name' => ['required', 'string', 'max:255'],
                'capacityKw' => ['required', 'numeric', 'gt:0'],
            ],
            'consumption' => [
                'name' => ['required', 'string', 'max:255'],
                'averageDemandKwh' => ['required', 'numeric', 'min:0'],
            ],
            'battery' => [
                'name' => ['required', 'string', 'max:255'],
                'capacityKwh' => ['required', 'numeric', 'gt:0'],
                'socPercent' => ['required', 'numeric', 'min:0', 'max:100'],
                'minSoc' => ['required', 'numeric', 'min:0', 'max:100'],
                'maxSoc' => ['required', 'numeric', 'min:0', 'max:100', 'gte:minSoc'],
                'efficiencyRate' => ['required', 'numeric', 'min:0', 'max:1'],
                'replacementCostTl' => ['required', 'numeric', 'gt:0'],
            ],
            default => [],
        };
    }

    protected function messages(): array
    {
        return [
            'capacityKw.gt' => 'Kapasite 0\'dan büyük olmalı.',
            'capacityKwh.gt' => 'Kapasite 0\'dan büyük olmalı.',
            'averageDemandKwh.min' => 'Tüketim negatif olamaz.',
            'socPercent.max' => 'SOC 0-100 arasında olmalı.',
            'maxSoc.gte' => 'Maksimum SOC, minimum SOC\'den küçük olamaz.',
            'replacementCostTl.gt' => 'Değiştirme maliyeti 0\'dan büyük olmalı.',
        ];
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, ['solar', 'wind', 'battery', 'consumption'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->resetForm();
        $this->syncFormWithTab();
    }

    public function edit(string $id): void
    {
        $record = $this->currentRecords()->first(fn ($item) => $item->getId() === $id);

        if ($record === null) {
            return;
        }

        $this->editingId = $id;
        $this->name = $record->getName();

        match ($this->tab) {
            'solar', 'wind' => $this->capacityKw = (string) $record->getCapacityKw(),
            'consumption' => $this->averageDemandKwh = (string) $record->getAverageDemandKwh(),
            default => null,
        };
    }

    public function save(): void
    {
        $this->validate();

        match ($this->tab) {
            'solar' => $this->assetService->saveSolarPlant($this->editingId, $this->name, (float) $this->capacityKw),
            'wind' => $this->assetService->saveWindPlant($this->editingId, $this->name, (float) $this->capacityKw),
            'consumption' => $this->assetService->saveConsumptionPoint($this->editingId, $this->name, (float) $this->averageDemandKwh),
            'battery' => $this->assetService->saveBattery(
                $this->name,
                (float) $this->capacityKwh,
                (float) $this->socPercent,
                (float) $this->minSoc,
                (float) $this->maxSoc,
                (float) $this->efficiencyRate,
                (float) $this->replacementCostTl,
            ),
            default => null,
        };

        $this->resetForm();
        $this->syncFormWithTab();
    }

    public function delete(string $id): void
    {
        match ($this->tab) {
            'solar' => $this->assetService->deleteSolarPlant($id),
            'wind' => $this->assetService->deleteWindPlant($id),
            'consumption' => $this->assetService->deleteConsumptionPoint($id),
            default => null,
        };

        if ($this->editingId === $id) {
            $this->resetForm();
        }
    }

    public function deleteBattery(): void
    {
        $this->assetService->deleteBattery();
        $this->resetForm();
    }

    public function cancelEdit(): void
    {
        $this->resetForm();
        $this->syncFormWithTab();
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.assets.asset-manager', [
            'records' => $this->currentRecords(),
            'battery' => $this->tab === 'battery' ? $this->assetService->getBattery() : null,
        ]);
    }

    private function syncFormWithTab(): void
    {
        if ($this->tab !== 'battery') {
            return;
        }

        $battery = $this->assetService->getBattery();

        if ($battery === null) {
            // No battery yet — pre-fill a rough, editable starting suggestion
            // instead of leaving the field blank (mentor recommendation #2).
            $this->replacementCostTl = (string) $this->assetService->suggestedReplacementCostTl(100.0);

            return;
        }

        $this->editingId = $battery->getId();
        $this->name = $battery->getName();
        $this->capacityKwh = (string) $battery->getCapacityKwh();
        $this->socPercent = (string) $battery->getSocPercent();
        $this->minSoc = (string) $battery->getMinSoc();
        $this->maxSoc = (string) $battery->getMaxSoc();
        $this->efficiencyRate = (string) $battery->getEfficiencyRate();
        $this->replacementCostTl = (string) $battery->getReplacementCostTl();
    }

    private function currentRecords(): Collection
    {
        return match ($this->tab) {
            'solar' => collect($this->assetService->listSolarPlants()),
            'wind' => collect($this->assetService->listWindPlants()),
            'consumption' => collect($this->assetService->listConsumptionPoints()),
            default => collect(),
        };
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->capacityKw = '';
        $this->averageDemandKwh = '';
        $this->capacityKwh = '';
        $this->socPercent = '';
        $this->minSoc = '';
        $this->maxSoc = '';
        $this->efficiencyRate = '';
        $this->replacementCostTl = '';
        $this->resetValidation();
    }
}
