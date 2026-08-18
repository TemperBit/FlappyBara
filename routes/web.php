<?php

use App\Http\Controllers\GameController;
use App\Http\Controllers\GameRunController;
use App\Http\Controllers\RaceRoomController;
use Illuminate\Support\Facades\Route;

Route::get('/', GameController::class)->name('home');

Route::middleware('auth')->group(function () {
    Route::post('runs', [GameRunController::class, 'store'])
        ->middleware('throttle:30,1')
        ->name('runs.store');

    Route::post('races', [RaceRoomController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('races.store');
    Route::get('races/{raceRoom}', [RaceRoomController::class, 'show'])
        ->name('races.show');
    Route::post('races/{raceRoom}/start', [RaceRoomController::class, 'start'])
        ->middleware('throttle:10,1')
        ->name('races.start');
});

Route::middleware('auth')->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
