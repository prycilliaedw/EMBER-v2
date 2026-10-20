<?php

use App\Http\Controllers\Cms\AboutController;
use App\Http\Controllers\Cms\ActivityController;
use App\Http\Controllers\Cms\AuthController;
use App\Http\Controllers\Cms\FaqController;
use App\Http\Controllers\Cms\GeoJsonController;
use App\Http\Controllers\Cms\LocationController;
use App\Http\Controllers\Cms\MethodologyController;
use App\Http\Controllers\Cms\ReferenceController;
use App\Http\Controllers\Cms\TeamController;
use App\Http\Controllers\User\ContentController;
use App\Http\Controllers\User\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DashboardController::class, 'index'])
    ->name('user.dashboard');
Route::get('/map', [ContentController::class, 'map'])->name('user.map');
Route::get('/data', [ContentController::class, 'data'])->name('user.data.index');
Route::get('/data/titik-lokasi.csv', [ContentController::class, 'downloadLocationCsv'])->name('user.data.download');
Route::get('/statistics', [ContentController::class, 'statistics'])->name('user.statistics');
Route::get('/locations/{id}', [ContentController::class, 'location'])->name('user.locations.show');
Route::get('/search', [ContentController::class, 'search'])->name('user.search');
Route::get('/about', [ContentController::class, 'about'])->name('user.about');
Route::get('/team', [ContentController::class, 'team'])->name('user.team');
Route::get('/methodology', [ContentController::class, 'methodology'])->name('user.methodology');
Route::get('/faq', [ContentController::class, 'faq'])->name('user.faq');
Route::get('/activities', [ContentController::class, 'activities'])->name('user.activities');
Route::get('/activities/{id}', [ContentController::class, 'activity'])->name('user.activities.show');
Route::get('/map-layers/{id}', [GeoJsonController::class, 'show'])->name('map-layers.show');

Route::prefix('cms')->name('cms.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1')
            ->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::get('/', fn () => redirect()->route('cms.locations.index'))->name('dashboard');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/about', [AboutController::class, 'edit'])->name('about.edit');
        Route::put('/about', [AboutController::class, 'update'])->name('about.update');

        Route::get('/methodology', [MethodologyController::class, 'edit'])->name('methodology.edit');
        Route::put('/methodology', [MethodologyController::class, 'update'])->name('methodology.update');

        Route::get('/team', [TeamController::class, 'index'])->name('team.index');
        Route::post('/team', [TeamController::class, 'store'])->name('team.store');
        Route::delete('/team/{id}', [TeamController::class, 'destroy'])->name('team.destroy');

        Route::get('/faq', [FaqController::class, 'index'])->name('faq.index');
        Route::post('/faq', [FaqController::class, 'store'])->name('faq.store');
        Route::get('/faq/{id}/edit', [FaqController::class, 'edit'])->name('faq.edit');
        Route::put('/faq/{id}', [FaqController::class, 'update'])->name('faq.update');
        Route::delete('/faq/{id}', [FaqController::class, 'destroy'])->name('faq.destroy');

        Route::get('/activities', [ActivityController::class, 'index'])->name('activities.index');
        Route::get('/activities/create', [ActivityController::class, 'create'])->name('activities.create');
        Route::post('/activities', [ActivityController::class, 'store'])->name('activities.store');
        Route::get('/activities/{id}/edit', [ActivityController::class, 'edit'])->name('activities.edit');
        Route::put('/activities/{id}', [ActivityController::class, 'update'])->name('activities.update');
        Route::delete('/activities/{id}', [ActivityController::class, 'destroy'])->name('activities.destroy');

        Route::get('/reference', [ReferenceController::class, 'index'])->name('references.index');
        Route::get('/references/picker', [ReferenceController::class, 'picker'])->name('references.picker');
        Route::post('/reference', [ReferenceController::class, 'store'])->name('references.store');
        Route::delete('/reference/{id}', [ReferenceController::class, 'destroy'])->name('references.destroy');

        Route::get('/geojson', [GeoJsonController::class, 'index'])->name('geojson.index');
        Route::post('/geojson/upload-chunk', [GeoJsonController::class, 'uploadChunk'])->name('geojson.upload-chunk');
        Route::post('/geojson', [GeoJsonController::class, 'store'])->name('geojson.store');
        Route::patch('/geojson/{id}', [GeoJsonController::class, 'update'])->name('geojson.update');
        Route::delete('/geojson/{id}', [GeoJsonController::class, 'destroy'])->name('geojson.destroy');

        Route::get('/titik-lokasi', [LocationController::class, 'index'])->name('locations.index');
        Route::get('/titik-lokasi/create', [LocationController::class, 'create'])->name('locations.create');
        Route::get('/titik-lokasi/template-csv', [LocationController::class, 'downloadCsvTemplate'])->name('locations.template');
        Route::get('/titik-lokasi/template-ember-enriched', [LocationController::class, 'downloadEnrichedCsvTemplate'])->name('locations.template-ember-enriched');
        Route::post('/titik-lokasi', [LocationController::class, 'store'])->name('locations.store');
        Route::get('/titik-lokasi/{id}', [LocationController::class, 'show'])->name('locations.show');
        Route::get('/titik-lokasi/{id}/edit', [LocationController::class, 'edit'])->name('locations.edit');
        Route::put('/titik-lokasi/{id}', [LocationController::class, 'update'])->name('locations.update');

        Route::get('/map-input', fn () => redirect()->route('cms.locations.index'))->name('map-input');
    });
});
