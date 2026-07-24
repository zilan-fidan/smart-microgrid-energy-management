<?php

use App\Livewire\Assets\AssetManager;
use App\Livewire\Dashboard;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');
Route::get('/assets', AssetManager::class)->name('assets');
