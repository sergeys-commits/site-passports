<?php

use App\Http\Controllers\DeploymentConsoleController;
use App\Http\Controllers\Deployments\PromoteToProductionController;
use App\Http\Controllers\Deployments\ThemeUpdateController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', fn () => redirect()->route('sites.index'))->name('dashboard');

    Route::get('/sites', [SiteController::class, 'index'])->name('sites.index');
    Route::post('/sites', [SiteController::class, 'store'])->name('sites.store');
    Route::get('/sites/{site}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('/sites/{site}/edit', [SiteController::class, 'edit'])->name('sites.edit');
    Route::patch('/sites/{site}', [SiteController::class, 'update'])->name('sites.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/sites/onboard-prod/new', [DeploymentConsoleController::class, 'onboardProdForm'])->name('deployments.onboard_prod.new');
    Route::post('/sites/onboard-prod', [DeploymentConsoleController::class, 'onboardProdStore'])->name('deployments.onboard_prod.store');
    Route::get('/deployments', [DeploymentConsoleController::class, 'deploymentsIndex'])->name('deployments.index');

    Route::get('/deployments/stage-provision/new', [DeploymentConsoleController::class, 'stageProvisionForm'])->name('deployments.stage_provision.new');
    Route::post('/deployments/stage-provision', [DeploymentConsoleController::class, 'stageProvisionStore'])->name('deployments.stage_provision.store');
    Route::get('/deployments/runs/{run}', [DeploymentConsoleController::class, 'runShow'])->name('deployments.runs.show');

    Route::get('/deployments/promote/new', [PromoteToProductionController::class, 'create'])->name('promote.create');
    Route::post('/deployments/promote', [PromoteToProductionController::class, 'store'])->name('promote.store');

    Route::prefix('deployments/theme-update')->name('theme-update.')->group(function () {
        Route::get('/new', [ThemeUpdateController::class, 'create'])->name('create');
        Route::post('/run', [ThemeUpdateController::class, 'run'])->name('run');
    });

});

require __DIR__.'/auth.php';
