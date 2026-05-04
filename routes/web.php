<?php

use App\Http\Controllers\AnaliseInvestigationPdfController;
use App\Http\Controllers\PlantaoPdfController;
use Illuminate\Support\Facades\Route;
use Filament\Facades\Filament;
use App\Http\Controllers\GoogleCalendarController;

Route::get('/', function () {
   // return view('welcome');
    return redirect()->to(Filament::getUrl());
});

Route::middleware('auth')->group(function (): void {
    Route::get('/google-calendar/connect', [GoogleCalendarController::class, 'redirect'])
        ->name('google-calendar.connect');

    Route::get('/google-calendar/callback', [GoogleCalendarController::class, 'callback'])
        ->name('google-calendar.callback');

    Route::post('/google-calendar/disconnect', [GoogleCalendarController::class, 'disconnect'])
        ->name('google-calendar.disconnect');

    Route::get('/analises/investigacoes/{investigation}/pdf', AnaliseInvestigationPdfController::class)
        ->name('analises.investigacoes.pdf');

    Route::get('/plantao/pdf', PlantaoPdfController::class)
        ->name('plantao.pdf');
});
