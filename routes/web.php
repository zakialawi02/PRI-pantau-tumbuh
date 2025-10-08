<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MapController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PlansController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FieldAreaController;
use App\Http\Controllers\ImageryDataController;
use App\Http\Controllers\UserCreditsController;
use App\Http\Controllers\Socialite\ProviderCallbackController;
use App\Http\Controllers\Socialite\ProviderRedirectController;

Route::get('/auth/{provider}/redirect', ProviderRedirectController::class)->name('auth.redirect');
Route::get('/auth/{provider}/callback', ProviderCallbackController::class)->name('auth.callback');

Route::prefix('dashboard')->name('admin.')->group(function () {
    Route::middleware(['auth', 'verified', 'role:superadmin'])->group(function () {
        Route::resource('users', UserController::class)->except('create', 'edit');
        Route::get('user-credits', [UserCreditsController::class, 'index'])->name('user-credits.index');
        Route::get('user-credits/{user}/add-credits', [UserCreditsController::class, 'showAddCreditsForm'])->name('user-credits.showAddCreditsForm');
        Route::post('user-credits/{user}/add-credits', [UserCreditsController::class, 'addCredits'])->name('user-credits.addCredits');
    });

    Route::middleware(['auth', 'verified', 'role:superadmin,admin'])->group(function () {
        Route::resource('plans', PlansController::class)->except('create', 'edit');

        Route::put('/payment/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payment.updateStatus');
        Route::get('/payment/{payment}/data', [PaymentController::class, 'getPaymentData'])->name('payment.getData');
    });

    Route::middleware(['auth', 'verified'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // Payment
        Route::get('/payment', [PaymentController::class, 'index'])->name('payment.index');
        Route::get('/payment/{payment}', [PaymentController::class, 'showPayment'])->name('payment.show');
        Route::post('/payment/{payment}/paypal', [PaymentController::class, 'processPayPalPayment'])->name('payment.process.paypal');
        Route::post('/payment/{payment}/upload-proof', [PaymentController::class, 'uploadProof'])->name('payment.uploadProof');
        Route::get('/payment/callback/{gateway}', [PaymentController::class, 'handleGatewayCallback'])->name('payment.callback');

        // field areas
        Route::get('/field-area', [FieldAreaController::class, 'index'])->name('field-area.index');
        Route::get('/field-area/{fieldArea}', [FieldAreaController::class, 'show'])->name('fieldArea.show');

        // Imagery
        Route::get('/imagery', [ImageryDataController::class, 'index'])->name('imagery.index');
        Route::get('/imagery/upload', [ImageryDataController::class, 'create'])->name('imagery.upload');
        Route::delete('/imagery/{imagery}', [ImageryDataController::class, 'destroy'])->name('imagery.destroy');
        Route::post('/imagery/{imagery}/retry-processing', [ImageryDataController::class, 'retryProcessing'])->name('imagery.retry');
        Route::get('/imagery/{imagery}/download-source', [ImageryDataController::class, 'downloadSource'])->name('imagery.download.source');
        Route::get('/imagery/{imagery}/download-result', [ImageryDataController::class, 'downloadResult'])->name('imagery.download.result');

        // Credit Purchase
        Route::get('/purchase-credits', [UserCreditsController::class, 'purchase'])->name('purchase-credits');
    });


    Route::middleware(['auth'])->group(function () {
        // Profile
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::patch('/photo-profile', [ProfileController::class, 'updatePhoto'])->name('profile.photo-update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
});

Route::middleware(['auth'])->group(function () {
    Route::post('/imagery-upload-chunk', [ImageryDataController::class, 'uploadChunk'])->name('upload.chunk');
    Route::post('/imagery-merge-chunks', [ImageryDataController::class, 'mergeChunks'])->name('upload.merge');
    Route::get('/imagery-check-progress', [ImageryDataController::class, 'checkProgress'])->name('upload.progress');
    Route::get('/imagery/list', [ImageryDataController::class, 'listUserImagery'])->name('imagery.list');
    Route::get('/user/credits/check', [UserCreditsController::class, 'checkUserCredits'])->name('user.credits.check');

    Route::post('/imagery-order', [ImageryDataController::class, 'imageryOrder'])->name('imageryOrder');
    Route::get('/imagery-checkout', [ImageryDataController::class, 'imageryCheckout'])->name('imageryCheckout');
    Route::post('/imagery-checkout', [ImageryDataController::class, 'processCheckoutImagery'])->name('processImageryCheckout');

    Route::post('/checkout/purchase-credits', [UserCreditsController::class, 'orderCredit'])->name('orderCredit');
    Route::get('/checkout', [UserCreditsController::class, 'checkoutOrder'])->name('checkoutOrder');
    Route::post('/checkout', [PaymentController::class, 'checkoutCredits'])->name('checkoutCredit.payment');
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

Route::get('/sandbox-payment', function () {
    return view('pages.front.order.sandbox-payment');
})->name('sandbox.payment');

Route::get('/privacy-policy', function () {
    return view('pages.front.privacy-policy');
})->name('privacy-policy');

Route::get('/terms-of-service', function () {
    return view('pages.front.terms-of-service');
})->name('terms-of-service');

Route::get('/purchase-credits', [UserCreditsController::class, 'purchasePublic'])->name('purchase-credits.public');

Route::get('/app/imagery', [MapController::class, 'index'])->name('appMap');

require __DIR__ . '/auth.php';
