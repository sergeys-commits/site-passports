<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DeploymentConsoleController;


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
Route::get('/deployments/stage-provision/new', [DeploymentConsoleController::class, 'stageProvisionForm'])->name('deployments.stage_provision.new');
Route::post('/deployments/stage-provision', [DeploymentConsoleController::class, 'stageProvisionStore'])->name('deployments.stage_provision.store');
Route::get('/deployments/runs/{run}', [DeploymentConsoleController::class, 'runShow'])->name('deployments.runs.show');


});

require __DIR__.'/auth.php';
