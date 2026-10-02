<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\AnalyticsController;
use App\Http\Controllers\Admin\ChatController as AdminChatController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DisputeController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\GHNWebhookController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\McpController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SellerListingController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\User\GHNController;
use App\Http\Controllers\User\MomoController;
use App\Http\Controllers\User\OrderController;
use App\Http\Controllers\User\SupportChatController;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/cho', [ListingController::class, 'index'])->name('listings.index');
Route::post('/ai/care', [AiController::class, 'care'])->middleware(['auth', 'throttle:30,1'])->name('ai.care');
Route::post('/mcp', [McpController::class, 'handle'])->middleware(['auth', 'throttle:40,1'])->name('mcp');
Route::post('/vi-tri', [LocationController::class, 'store'])->middleware('throttle:30,1')->name('location.store');
Route::match(['get', 'post'], '/khu-vuc', [LocationController::class, 'storeArea'])->middleware('throttle:60,1')->name('area.store');
Route::get('/tin/{listing:slug}', [ListingController::class, 'show'])->name('listings.show');
Route::get('/cua-hang/{user}', [ShopController::class, 'show'])->name('shops.show');
Route::get('/p/{page}', [PageController::class, 'show'])->name('pages.show');

Route::middleware('guest')->group(function () {
    Route::get('register', [AuthController::class, 'showRegistrationForm'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
    Route::get('login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:8,1');
    Route::get('forgot-password', [AuthController::class, 'showForgotForm'])->name('password.request');
    Route::post('forgot-password', [AuthController::class, 'sendResetLink'])->middleware('throttle:6,1')->name('password.email');
    Route::get('reset-password/{token}', [AuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.update');
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::get('/email/verify', function () {
    return view('auth.verify-email');
})->middleware('auth')->name('verification.notice');

Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request) {
    $request->fulfill();

    return redirect()->route('home')->with('success', 'Xác thực email thành công. Bạn có thể mua hàng và đăng bán.');
})->middleware(['auth', 'signed'])->name('verification.verify');

Route::post('/email/verification-notification', [AuthController::class, 'sendVerification'])
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.send');
Route::post('/email/verification-code', [AuthController::class, 'confirmVerification'])
    ->middleware(['auth', 'throttle:10,1'])
    ->name('verification.confirm');

Route::post('/ghn/webhook', [GHNWebhookController::class, 'handle'])->name('ghn.webhook');
Route::post('/payment/momo/ipn', [MomoController::class, 'ipn'])->name('payment.momo.ipn');
Route::get('/payment/momo/callback', [MomoController::class, 'callback'])->name('user.payment.momo.callback');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::post('/ai', [AiController::class, 'ops'])->middleware('throttle:40,1')->name('ai');
    Route::get('/listings', [AdminController::class, 'listings'])->name('listings');
    Route::post('/listings/{listing}/approve', [AdminController::class, 'approveListing'])->name('listings.approve');
    Route::post('/listings/{listing}/reject', [AdminController::class, 'rejectListing'])->name('listings.reject');
    Route::post('/listings/{listing}/hide', [AdminController::class, 'hideListing'])->name('listings.hide');
    Route::post('/listings/{listing}/verify-origin', [AdminController::class, 'verifyOrigin'])->name('listings.verify');
    Route::post('/listings/{listing}/delete', [AdminController::class, 'destroyListing'])->name('listings.destroy');
    Route::get('/users', [AdminController::class, 'users'])->name('users');
    Route::post('/users/{user}/seller', [AdminController::class, 'toggleSeller'])->name('users.seller');
    Route::post('/users/{user}/lock', [AdminController::class, 'lockUser'])->name('users.lock');
    Route::post('/users/{user}/unlock', [AdminController::class, 'unlockUser'])->name('users.unlock');
    Route::post('/users/{user}/delete', [AdminController::class, 'destroyUser'])->name('users.destroy');
    Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus'])->name('orders.updateStatus');
    Route::post('/orders/{order}/cancel', [AdminOrderController::class, 'cancel'])->name('orders.cancel');
    Route::delete('/orders/{order}', [AdminOrderController::class, 'destroy'])->name('orders.destroy');
    Route::get('/chat', [AdminChatController::class, 'index'])->name('chat.index');
    Route::get('/chat/{user}', [AdminChatController::class, 'show'])->name('chat.show');
    Route::post('/chat/{user}', [AdminChatController::class, 'store'])->name('chat.store');
    Route::get('/reports', [AdminController::class, 'reports'])->name('reports');
    Route::post('/reports/{report}/close', [AdminController::class, 'closeReport'])->name('reports.close');
    Route::get('/categories', [AdminController::class, 'categories'])->name('categories');
    Route::post('/categories', [AdminController::class, 'storeCategory'])->name('categories.store');
    Route::put('/categories/{category}', [AdminController::class, 'updateCategory'])->name('categories.update');
    Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory'])->name('categories.destroy');
    Route::get('/kyc', [AdminController::class, 'kyc'])->name('kyc');
    Route::post('/kyc/{user}/approve', [AdminController::class, 'approveKyc'])->name('kyc.approve');
    Route::post('/kyc/{user}/reject', [AdminController::class, 'rejectKyc'])->name('kyc.reject');
    Route::get('/finance', [FinanceController::class, 'index'])->name('finance');
    Route::get('/finance/transactions', [FinanceController::class, 'transactions'])->name('finance.transactions');
    Route::get('/finance/export', [FinanceController::class, 'export'])->name('finance.export');
    Route::get('/finance/vi', [AdminController::class, 'finance'])->name('finance.wallet');
    Route::patch('/finance/{order}/status', [FinanceController::class, 'updateStatus'])->name('finance.update-status');
    Route::post('/finance/vi/{transaction}/approve', [AdminController::class, 'approveWallet'])->name('finance.approve');
    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics');
    Route::get('/analytics/export', [AnalyticsController::class, 'export'])->name('analytics.export');
    Route::get('/disputes', [AdminController::class, 'disputes'])->name('disputes');
    Route::post('/disputes/{dispute}/resolve', [AdminController::class, 'resolveDispute'])->name('disputes.resolve');
});

Route::middleware('deny.admin.shop')->group(function () {
    Route::get('/gio-hang', [CartController::class, 'index'])->name('user.cart.index');
    Route::delete('/gio-hang/{key}', [CartController::class, 'remove'])->name('user.cart.remove');
});

Route::middleware('auth')->group(function () {
    Route::post('/tin/{listing:slug}/bao-cao', [ListingController::class, 'report'])->name('listings.report');
    Route::post('/tin/{listing:slug}/chat', [ConversationController::class, 'start'])->name('messages.start');
    Route::post('/tin/{listing:slug}/yeu-thich', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::post('/tin/{listing:slug}/danh-gia', [AccountController::class, 'storeReview'])->name('reviews.store');

    Route::get('/ho-so', [AccountController::class, 'profile'])->name('account.profile');
    Route::get('/yeu-thich', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::get('/ho-so/thay-doi', [AccountController::class, 'changeIndex'])->name('account.change.index');
    Route::get('/ho-so/thay-doi/xac-thuc', [AccountController::class, 'changeConfirm'])->name('account.change.confirm');
    Route::post('/ho-so/thay-doi/xac-thuc', [AccountController::class, 'changeApply'])->middleware('throttle:10,1')->name('account.change.apply');
    Route::post('/ho-so/thay-doi/gui-lai', [AccountController::class, 'changeResend'])->middleware('throttle:5,1')->name('account.change.resend');
    Route::post('/ho-so/thay-doi/huy', [AccountController::class, 'changeCancel'])->name('account.change.cancel');
    Route::get('/ho-so/thay-doi/{field}', [AccountController::class, 'changeForm'])->name('account.change.form');
    Route::post('/ho-so/thay-doi/{field}', [AccountController::class, 'changeStart'])->middleware('throttle:5,1')->name('account.change.start');
    Route::get('/ho-tro', [SupportChatController::class, 'index'])->name('support.index');
    Route::post('/ho-tro', [SupportChatController::class, 'store'])->name('support.store');
    Route::get('/tin-nhan', [ConversationController::class, 'index'])->name('messages.index');
    Route::get('/tin-nhan/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::get('/tin-nhan/{conversation}/moi', [ConversationController::class, 'poll'])->name('messages.poll');
    Route::post('/tin-nhan/{conversation}', [ConversationController::class, 'reply'])->name('messages.reply');
    Route::post('/tin-nhan/{conversation}/chap-nhan', [ConversationController::class, 'acceptOffer'])->name('messages.accept');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::post('/tin/{listing:slug}/gio', [CartController::class, 'add'])->middleware('deny.admin.shop')->name('user.cart.add');
    Route::prefix('locations')->name('locations.')->group(function () {
        Route::get('/provinces', [GHNController::class, 'getProvinces'])->name('provinces');
        Route::get('/districts/{provinceId}', [GHNController::class, 'getDistricts'])->name('districts');
        Route::get('/wards/{districtId}', [GHNController::class, 'getWards'])->name('wards');
        Route::post('/calculate-fee', [GHNController::class, 'getShippingFee'])->name('fee');
    });

    Route::get('/thanh-toan', [OrderController::class, 'checkout'])->middleware('deny.admin.shop')->name('user.payment.index');
    Route::post('/thanh-toan', [OrderController::class, 'process'])->middleware('deny.admin.shop')->name('user.payment.process');
    Route::get('/don-hang', [OrderController::class, 'history'])->name('user.orders.index');
    Route::get('/don-hang/{order}', [OrderController::class, 'show'])->name('user.orders.show');
    Route::post('/don-hang/{order}/huy', [OrderController::class, 'cancel'])->name('user.orders.cancel');
    Route::post('/don-hang/{order}/nhan-hang', [OrderController::class, 'confirmReceived'])->name('user.orders.receive');
    Route::post('/don-hang/{order}/khieu-nai', [DisputeController::class, 'store'])->name('user.orders.dispute');
    Route::get('/don-hang/{order}/momo', [MomoController::class, 'payAgain'])->middleware('deny.admin.shop')->name('user.orders.momo.pay');
    Route::get('/don-hang/{order}/momo/start', [MomoController::class, 'start'])->middleware('deny.admin.shop')->name('user.orders.momo.start');

    Route::get('/ho-so/kyc', [KycController::class, 'show'])->name('account.kyc');
    Route::post('/ho-so/kyc', [KycController::class, 'store'])->name('account.kyc.store');

    Route::get('/ban', [SellerListingController::class, 'index'])->name('seller.listings.index');
    Route::get('/ban/dang-tin', [SellerListingController::class, 'create'])->name('seller.listings.create');
    Route::post('/ban/dang-tin', [SellerListingController::class, 'store'])->name('seller.listings.store');
    Route::post('/ban/uoc-gia', [SellerListingController::class, 'estimate'])->middleware('throttle:20,1')->name('seller.estimate');
    Route::get('/ban/{listing}/sua', [SellerListingController::class, 'edit'])->name('seller.listings.edit');
    Route::put('/ban/{listing}', [SellerListingController::class, 'update'])->name('seller.listings.update');
    Route::post('/ban/{listing}/an', [SellerListingController::class, 'hide'])->name('seller.listings.hide');
    Route::post('/ban/{listing}/hien', [SellerListingController::class, 'publish'])->name('seller.listings.publish');
    Route::post('/ban/{listing}/da-ban', [SellerListingController::class, 'markSold'])->name('seller.listings.sold');
    Route::post('/ban/{listing}/day-tin', [SellerListingController::class, 'boost'])->name('seller.listings.boost');
});
