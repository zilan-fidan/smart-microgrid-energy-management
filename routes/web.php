<?php

use App\Livewire\Assets\AssetManager;
use App\Livewire\Dashboard;
use App\Livewire\Simulation\SimulationPanel;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/assets', AssetManager::class)->name('assets');
Route::get('/simulation', SimulationPanel::class)->name('simulation');
