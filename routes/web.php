<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SubscriptionController;


Route::prefix('dashboard')->name('admin.')->group(function () {
    Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
        Route::resource('users', UserController::class)->except('create', 'edit');

        Route::resource('plans', PlansController::class)->except('create', 'edit');
    });

    Route::middleware(['auth', 'verified', 'role:superadmin,admin'])->group(function () {
        Route::put('/payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payment.updateStatus');
        Route::get('/payments/{payment}/data', [PaymentController::class, 'getPaymentData'])->name('payment.getData');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/photo-profile', [ProfileController::class, 'updatePhoto'])->name('profile.photo-update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

        // Payment
        Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
        Route::get('/payment/{payment}', [PaymentController::class, 'showPayment'])->name('payment.show');

        // subscription
        Route::get('/my-subscription', [SubscriptionController::class, 'index'])->name('subscription.index');
    });
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/map-order', [PaymentController::class, 'mapOrder'])->name('mapOrder');
    Route::get('/checkout', [PaymentController::class, 'checkoutOrder'])->name('checkoutOrder');
    Route::post('/checkout', [PaymentController::class, 'checkout'])->name('checkout.payment');
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

Route::get('/app/imagery', [MapController::class, 'index'])->name('appMap');

require __DIR__ . '/auth.php';
