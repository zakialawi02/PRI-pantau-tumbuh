<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;


Route::prefix('dashboard')->name('admin.')->group(function () {
    Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
        Route::resource('users', UserController::class)->except('create', 'edit');
    });

    Route::middleware(['auth', 'verified', 'role:superadmin,admin'])->group(function () {
        Route::get('/requestContributor', [UserController::class, 'requestContributor'])->name('requestContributor.index');
        Route::delete('/requestContributor/{requestContributor:id}', [UserController::class, 'destroyRequestContributor'])->name('requestContributor.destroy');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/photo-profile', [ProfileController::class, 'updatePhoto'])->name('profile.photo-update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});


Route::get('/admin', function () {
    return redirect('/dashboard');
});

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/docs', function () {
    return view('pages.docs');
})->name('docs');

Route::get('/team', function () {
    return view('pages.front.team');
})->name('team');
Route::get('/contact', function () {
    return view('pages.front.contact');
})->name('contact');
Route::get('/about-pri', function () {
    return view('pages.front.tentangPRI');
})->name('about-pri');
Route::get('/what-is-sentinel-2', function () {
    return view('pages.front.citraSentinel2');
})->name('what-is-sentinel-2');

Route::get('/pri-estimate-map', function () {
    return view('pages.front.petaEstimasiPRI');
})->name('peta-estimasi-pri');
Route::get('/high-resolution-pri-estimation-map-with-ai', function () {
    return view('pages.front.petaEstimasiPRIResolusiTinggi');
})->name('pri-estimation-map-ai');

Route::get('/buy-citra', function () {
    return view('pages.front.buyCitra');
})->name('buyCitra');

Route::get('/map', function () {
    return view('pages.front.map');
})->name('map');

Route::post('/before-checkout', [PaymentController::class, 'beforeCheckout'])->name('beforeCheckout');
Route::get('/checkout', [PaymentController::class, 'checkout'])->name('checkout');
Route::post('/checkout', [PaymentController::class, 'checkoutPayment'])->name('checkout.payment');

require __DIR__ . '/auth.php';
