<?php

use App\Http\Controllers\Marketing\BirthdayController;
use App\Http\Controllers\Marketing\CampaignController;
use App\Http\Controllers\Marketing\CouponController;
use App\Http\Controllers\Marketing\PromotionController;
use App\Http\Controllers\Marketing\TemplateController;
use Illuminate\Support\Facades\Route;

// Módulo Marketing: plantillas, cumpleaños, campañas, cupones y promociones
Route::prefix('admin/marketing')->name('marketing.')->group(function () {
    Route::prefix('templates')->name('templates.')->middleware('check-permission:marketing.view')->group(function () {
        Route::get('/', [TemplateController::class, 'index'])->name('index');
        Route::get('/create', [TemplateController::class, 'create'])->name('create')->middleware('check-permission:marketing.edit');
        Route::post('/', [TemplateController::class, 'store'])->name('store')->middleware('check-permission:marketing.edit');
        Route::get('/{template}/edit', [TemplateController::class, 'edit'])->name('edit')->middleware('check-permission:marketing.edit');
        Route::put('/{template}', [TemplateController::class, 'update'])->name('update')->middleware('check-permission:marketing.edit');
        Route::delete('/{template}', [TemplateController::class, 'destroy'])->name('destroy')->middleware('check-permission:marketing.edit');
    });

    Route::get('/birthdays', [BirthdayController::class, 'index'])->name('birthdays')->middleware('check-permission:marketing.view');

    Route::prefix('campaigns')->name('campaigns.')->middleware('check-permission:marketing.view')->group(function () {
        Route::get('/', [CampaignController::class, 'index'])->name('index');
        Route::get('/create', [CampaignController::class, 'create'])->name('create')->middleware('check-permission:marketing.edit');
        Route::post('/', [CampaignController::class, 'store'])->name('store')->middleware('check-permission:marketing.edit');
        Route::get('/{campaign}', [CampaignController::class, 'show'])->name('show');
        Route::post('/{campaign}/complete', [CampaignController::class, 'complete'])->name('complete')->middleware('check-permission:marketing.edit');
    });

    Route::prefix('coupons')->name('coupons.')->middleware('check-permission:coupons.view')->group(function () {
        Route::get('/', [CouponController::class, 'index'])->name('index');
        Route::get('/create', [CouponController::class, 'create'])->name('create')->middleware('check-permission:coupons.create');
        Route::post('/', [CouponController::class, 'store'])->name('store')->middleware('check-permission:coupons.create');
        Route::get('/{coupon}/edit', [CouponController::class, 'edit'])->name('edit')->middleware('check-permission:coupons.edit');
        Route::put('/{coupon}', [CouponController::class, 'update'])->name('update')->middleware('check-permission:coupons.edit');
        Route::delete('/{coupon}', [CouponController::class, 'destroy'])->name('destroy')->middleware('check-permission:coupons.delete');
    });

    Route::prefix('promotions')->name('promotions.')->middleware('check-permission:promotions.view')->group(function () {
        Route::get('/', [PromotionController::class, 'index'])->name('index');
        Route::get('/create', [PromotionController::class, 'create'])->name('create')->middleware('check-permission:promotions.create');
        Route::post('/', [PromotionController::class, 'store'])->name('store')->middleware('check-permission:promotions.create');
        Route::get('/{promotion}/edit', [PromotionController::class, 'edit'])->name('edit')->middleware('check-permission:promotions.edit');
        Route::put('/{promotion}', [PromotionController::class, 'update'])->name('update')->middleware('check-permission:promotions.edit');
        Route::delete('/{promotion}', [PromotionController::class, 'destroy'])->name('destroy')->middleware('check-permission:promotions.delete');
    });
});
