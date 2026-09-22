<?php
// prj/routes/web.php

use Illuminate\Support\Facades\Route;

// 蜈ｱ騾壹・隱崎ｨｼ邉ｻ
use App\Http\Controllers\Common\PageController;
use App\Http\Controllers\Common\ColumnArticleController;
use App\Http\Controllers\Common\SupportNoticeController;
use App\Http\Controllers\Common\SettingController;
use App\Http\Controllers\Common\TalkTemplateController;
use App\Http\Controllers\Common\DemoLoginController;
use App\Http\Controllers\Common\BankLookupController;
use App\Http\Controllers\Common\RegistrationController;
use App\Http\Controllers\Auth\Cast\LoginController as CastLogin;
use App\Http\Controllers\Auth\Shop\LoginController as ShopLogin;
use App\Http\Controllers\Auth\LineLoginController as LineLogin;
use App\Http\Controllers\Auth\PasswordResetController as PasswordReset;
use App\Http\Controllers\Auth\EmailVerificationController as EmailVerify;
use App\Http\Controllers\LineWebhookController;
use App\Http\Controllers\Common\TalkController as TalkController;
use App\Http\Controllers\Common\NotificationController as CommonNotification;

// 邂｡逅・・ｼ医ヰ繝・け繧ｪ繝輔ぅ繧ｹ・・
use App\Http\Controllers\Admin\DashboardController as AdminDashboard;
use App\Http\Controllers\Admin\DepositController as AdminDeposit;
use App\Http\Controllers\Admin\InvoiceController as AdminInvoice;
use App\Http\Controllers\Admin\SalesController as AdminSales;
use App\Http\Controllers\Admin\MasterController as AdminMaster;
use App\Http\Controllers\Admin\ColumnController as AdminColumn;
use App\Http\Controllers\Admin\InquiryController as AdminInquiry;
use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Admin\ShopController as AdminShop;
use App\Http\Controllers\Admin\CastController as AdminCast;
use App\Http\Controllers\Admin\NgWordController as AdminNgWord;
use App\Http\Controllers\Admin\NoticeController as AdminNotice;
use App\Http\Controllers\Admin\TaskController as AdminTask;
use App\Http\Controllers\Admin\AdminAccountController as AdminAccount;
use App\Http\Controllers\Admin\VerificationController as AdminVerification;
use App\Http\Controllers\Admin\BankController as AdminBank;
use App\Http\Controllers\Admin\PolicyController as AdminPolicy;
use App\Http\Controllers\Admin\NotificationSpecController as AdminNotificationSpec;
use App\Http\Controllers\Admin\CharacterGuideController as AdminCharacterGuide;
use App\Http\Controllers\Admin\TalkQuickReplyController as AdminTalkQuickReply;

// 蠎苓・蛛ｴ
use App\Http\Controllers\Common\DiscoveryController as DiscoveryHome;
use App\Http\Controllers\Shops\SearchController as ShopSearch;
use App\Http\Controllers\Shops\MypageController as ShopMypage;
use App\Http\Controllers\Shops\ProfileController as ShopProfile;
use App\Http\Controllers\Shops\RecruitmentController as ShopRecruit;
use App\Http\Controllers\Shops\ReviewController as ShopReview;
use App\Http\Controllers\Shops\InteractionController as ShopInteraction;
use App\Http\Controllers\Shops\StaffController as ShopStaff;

// 繧ｭ繝｣繧ｹ繝亥・
use App\Http\Controllers\Casts\ProfileController as CastProfile;
use App\Http\Controllers\Casts\MypageController as CastMypage;
use App\Http\Controllers\Casts\SearchController as CastSearch;
use App\Http\Controllers\Casts\RecruitmentController as CastRecruit;
use App\Http\Controllers\Casts\AiChatController as CastAiChat;

/*
|--------------------------------------------------------------------------
| 繝輔ぃ繝薙さ繝ｳ・・04髦ｲ豁｢・壹い繧､繧ｳ繝ｳ縺ｯ layout 縺ｮ link 縺ｧ謖・ｮ夲ｼ・
|--------------------------------------------------------------------------
*/
Route::get('/favicon.ico', function () {
    return response('', 204);
})->name('favicon');

/*
|--------------------------------------------------------------------------
| PWA Manifest・医せ繝槭・縺ｮ繧､繝ｳ繧ｹ繝医・繝ｫ蛻､螳夂畑・哺IME 縺ｨ逶ｸ蟇ｾURL・・
|--------------------------------------------------------------------------
*/
Route::get('/manifest.json', function () {
    // 未ログインでも 200 で開ける URL にする（Chrome のインストール判定が通りやすい）
    $startUrl = '/login/demo?utm_source=pwa';
    $manifest = [
        'name' => 'ミセチョク',
        'short_name' => 'ミセチョク',
        'description' => 'ミセチョク - デモ',
        'start_url' => $startUrl,
        'scope' => '/',
        'id' => $startUrl,
        'display' => 'standalone',
        'display_override' => ['standalone', 'minimal-ui', 'browser'],
        'orientation' => 'portrait-primary',
        'theme_color' => '#190509',
        'background_color' => '#190509',
        'lang' => 'ja',
        'prefer_related_applications' => false,
        'icons' => [
            ['src' => '/assets/images/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/images/pwa/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/assets/images/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/assets/images/pwa/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ],
    ];
    return response()->json($manifest, 200, [
        'Content-Type' => 'application/manifest+json',
        'Cache-Control' => 'public, max-age=3600',
    ]);
})->name('manifest');

/*
|--------------------------------------------------------------------------
| 繝ｪ繝繧､繝ｬ繧ｯ繝郁ｨｭ螳・
|--------------------------------------------------------------------------
*/
// ルートはサービス紹介の welcome へ
Route::redirect('/', '/welcome');
Route::view('/welcome', 'common.welcome')->name('welcome');
Route::view('/welcome/shop', 'lp.shop')->name('welcome.shop');
Route::redirect('/shop', '/shop/home');
Route::redirect('/cast', '/cast/home');
Route::redirect('/bk', '/admin');
Route::get('/bk/{path}', function ($path) {
    return redirect('/admin/' . $path);
})->where('path', '.*');

/*
|--------------------------------------------------------------------------
| Admin Portal (邂｡逅・・ｰら畑)
|--------------------------------------------------------------------------
|
| 蠎苓・繝ｻ繧ｭ繝｣繧ｹ繝医→縺ｯ蛻･縺ｮ繝励Ξ繝輔ぅ繝・け繧ｹ `/admin` 縺ｧ邂｡逅・判髱｢繧呈署萓帙☆繧九・
| 隱崎ｨｼ縺ｾ繧上ｊ縺ｯ莉雁ｾ梧僑蠑ｵ縺励ｄ縺吶＞繧医≧縺ｫ繝ｫ繝ｼ繝医ｒ蛻・屬縺励※縺翫￥縲・
|
*/
Route::prefix('admin')->name('admin.')->group(function () {

    // 繝ｭ繧ｰ繧､繝ｳ縺ｯ蜈ｱ騾・/login 縺ｫ邨ｱ荳・医Μ繝繧､繝ｬ繧ｯ繝医・縺ｿ・・
    // 運営（管理者）ログイン：本番用の独立URL
    Route::get('/login', [AdminAuth::class, 'showLoginForm'])->name('login');
    // Rate-limit: 5 attempts per 15 minutes per IP (brute-force protection)
    Route::post('/login', [AdminAuth::class, 'login'])
        ->middleware('throttle:5,15')
        ->name('login.post');
    Route::post('/logout', [AdminAuth::class, 'logout'])->name('logout');

    // 邂｡逅・判髱｢譛ｬ菴・
    Route::middleware([])->group(function () {
        Route::get('/', [AdminDashboard::class, 'index'])
            ->middleware('admin.permission:dashboard.view')
            ->name('dashboard');

        // 隲区ｱよ嶌逋ｺ陦・
        Route::middleware('admin.permission:operations.invoices')->group(function () {
            Route::get('/invoices', [AdminInvoice::class, 'index'])->name('invoices.index');
            Route::post('/invoices/issue-manual', [AdminInvoice::class, 'issueManual'])->name('invoices.issue-manual');
            Route::get('/invoices/template-settings', [AdminInvoice::class, 'templateSettings'])->name('invoices.template-settings');
            Route::post('/invoices/template-settings', [AdminInvoice::class, 'updateTemplateSettings'])->name('invoices.template-settings.update');
        });

        // 蜈･驥代・謖ｯ霎ｼ邂｡逅・
        Route::middleware('admin.permission:operations.deposits')->group(function () {
            Route::get('/deposits/invoice-template/download', [AdminDeposit::class, 'downloadInvoiceTemplate'])->name('deposits.invoice-template.download');
            Route::get('/deposits', [AdminDeposit::class, 'index'])->name('deposits.index');
            Route::get('/deposits/{deposit}/invoice', [AdminDeposit::class, 'showInvoice'])->name('deposits.invoice.show');
            Route::get('/deposits/{deposit}/invoice/pdf', [AdminDeposit::class, 'downloadInvoicePdf'])->name('deposits.invoice.pdf');
            Route::post('/deposits/{deposit}/invoice', [AdminDeposit::class, 'issueInvoice'])->name('deposits.invoice.issue');
            Route::post('/deposits/{deposit}/confirm-shop-payment', [AdminDeposit::class, 'confirmShopPayment'])->name('deposits.shop-payment.confirm');
            Route::post('/deposits/{deposit}/transfer-start', [AdminDeposit::class, 'transferStart'])->name('deposits.transfer-start');
            Route::post('/deposits/{deposit}/transfer-complete', [AdminDeposit::class, 'transferComplete'])->name('deposits.transfer-complete');
            Route::post('/deposits/{deposit}/payment-task-invalidate', [AdminDeposit::class, 'paymentTaskInvalidate'])->name('deposits.payment-task.invalidate');
            Route::post('/deposits/{deposit}/payment-task-refund-flag', [AdminDeposit::class, 'paymentTaskRefundFlag'])->name('deposits.payment-task.refund-flag');
            Route::post('/deposits/{deposit}/transfer-cast', [AdminDeposit::class, 'transferCast'])->name('deposits.cast-transfer.execute');
        });

        // Premiumプラン入金管理（振込の目視確認 → 有効化）
        Route::middleware('admin.permission:operations.deposits')->group(function () {
            Route::get('/plans', [\App\Http\Controllers\Admin\PlanSubscriptionController::class, 'index'])->name('plans.index');
            Route::post('/plans/{subscription}/confirm', [\App\Http\Controllers\Admin\PlanSubscriptionController::class, 'confirm'])->name('plans.confirm');
            Route::get('/plans/{subscription}/invoice', [\App\Http\Controllers\Admin\PlanSubscriptionController::class, 'downloadInvoice'])->name('plans.invoice');
            Route::get('/plans/{subscription}/receipt', [\App\Http\Controllers\Admin\PlanSubscriptionController::class, 'downloadReceipt'])->name('plans.receipt');
        });

        // 螢ｲ荳顔ｮ｡逅・
        Route::get('/sales', [AdminSales::class, 'index'])
            ->middleware('admin.permission:analytics.sales')
            ->name('sales.index');

        // 繝槭せ繧ｿ險ｭ螳夂ｮ｡逅・
        Route::middleware('admin.permission:master.masters')->group(function () {
            Route::get('/masters', [AdminMaster::class, 'index'])->name('masters.index');
            Route::post('/masters/catalogs/{catalogKey}', [AdminMaster::class, 'storeCatalog'])->name('masters.catalogs.store');
            // NOTE: {recordId} より先に定義すること（/reorder が recordId として解釈されるのを防ぐ）
            Route::patch('/masters/catalogs/{catalogKey}/reorder', [AdminMaster::class, 'reorderCatalog'])->name('masters.catalogs.reorder');
            Route::patch('/masters/catalogs/{catalogKey}/{recordId}', [AdminMaster::class, 'updateCatalog'])->name('masters.catalogs.update')->whereNumber('recordId');
            Route::delete('/masters/catalogs/{catalogKey}/{recordId}', [AdminMaster::class, 'destroyCatalog'])->name('masters.catalogs.destroy');
        });

        // 通知・タスク仕様の確認／変更
        Route::middleware('admin.permission:master.notification_spec')->group(function () {
            Route::get('/notification-spec', [AdminNotificationSpec::class, 'index'])->name('notification-spec.index');
            Route::put('/notification-spec/notifications/{key}', [AdminNotificationSpec::class, 'updateNotification'])
                ->where('key', '[A-Za-z0-9_.\-]+')
                ->name('notification-spec.notifications.update');
            Route::put('/notification-spec/reminders/{key}', [AdminNotificationSpec::class, 'updateReminder'])
                ->where('key', '[A-Za-z0-9_.\-]+')
                ->name('notification-spec.reminders.update');
            Route::put('/notification-spec/tasks/{key}', [AdminNotificationSpec::class, 'updateTask'])
                ->where('key', '[A-Za-z0-9_.\-]+')
                ->name('notification-spec.tasks.update');
        });

        // 蠎苓・邂｡逅・
        Route::middleware('admin.permission:accounts.shops.view')->group(function () {
            Route::get('/shops', [AdminShop::class, 'index'])->name('shops.index');
            Route::get('/shops/{shopId}', [AdminShop::class, 'show'])->name('shops.show');
            Route::post('/shops/{shopId}/lock-private', [AdminShop::class, 'lockPrivate'])->name('shops.lock-private');
            Route::post('/shops/{shopId}/unlock-private', [AdminShop::class, 'unlockPrivate'])
                ->middleware('admin.permission:accounts.shops.private')
                ->name('shops.unlock-private');
        });
        Route::middleware('admin.permission:accounts.shops.manage')->group(function () {
            Route::post('/shops/{shopId}/suspend', [AdminShop::class, 'suspend'])->name('shops.suspend');
            Route::post('/shops/{shopId}/unsuspend', [AdminShop::class, 'unsuspend'])->name('shops.unsuspend');
        });

        // 繧ｭ繝｣繧ｹ繝育ｮ｡逅・
        Route::middleware('admin.permission:accounts.casts.view')->group(function () {
            Route::get('/casts', [AdminCast::class, 'index'])->name('casts.index');
            Route::get('/casts/{castId}', [AdminCast::class, 'show'])->name('casts.show');
            Route::post('/casts/{castId}/lock-private', [AdminCast::class, 'lockPrivate'])->name('casts.lock-private');
            Route::post('/casts/{castId}/unlock-private', [AdminCast::class, 'unlockPrivate'])
                ->middleware('admin.permission:accounts.casts.private')
                ->name('casts.unlock-private');
        });
        Route::middleware('admin.permission:accounts.casts.manage')->group(function () {
            Route::post('/casts/{castId}/suspend', [AdminCast::class, 'suspend'])->name('casts.suspend');
            Route::post('/casts/{castId}/unsuspend', [AdminCast::class, 'unsuspend'])->name('casts.unsuspend');
        });

        // NG繝ｯ繝ｼ繝臥ｮ｡逅・
        Route::middleware('admin.permission:master.ngwords')->group(function () {
            Route::get('/ngwords', [AdminNgWord::class, 'index'])->name('ngwords.index');
            Route::post('/ngwords', [AdminNgWord::class, 'store'])->name('ngwords.store');
            Route::put('/ngwords/{id}', [AdminNgWord::class, 'update'])->whereNumber('id')->name('ngwords.update');
            Route::delete('/ngwords/{id}', [AdminNgWord::class, 'destroy'])->whereNumber('id')->name('ngwords.destroy');
        });

        // オコジョガイド設定
        Route::middleware('admin.permission:master.character_guide')->group(function () {
            Route::get('/character-guide', [AdminCharacterGuide::class, 'index'])->name('character-guide.index');
            Route::put('/character-guide', [AdminCharacterGuide::class, 'update'])->name('character-guide.update');
        });

        // トーククイック定型文マスタ (状況x役割ごとの候補文)
        Route::middleware('admin.permission:master.talk_quick_replies')->group(function () {
            Route::get('/talk-quick-replies', [AdminTalkQuickReply::class, 'index'])->name('talk-quick-replies.index');
            Route::put('/talk-quick-replies', [AdminTalkQuickReply::class, 'update'])->name('talk-quick-replies.update');
            Route::post('/talk-quick-replies/reset', [AdminTalkQuickReply::class, 'reset'])->name('talk-quick-replies.reset');
        });

        // 縺顔衍繧峨○邂｡逅・
        Route::middleware('admin.permission:content.notices')->group(function () {
            Route::get('/notices', [AdminNotice::class, 'index'])->name('notices.index');
            Route::get('/notices/create', [AdminNotice::class, 'create'])->name('notices.create');
            Route::post('/notices', [AdminNotice::class, 'store'])->name('notices.store');
            Route::get('/notices/{notice}/edit', [AdminNotice::class, 'edit'])->name('notices.edit');
            Route::put('/notices/{notice}', [AdminNotice::class, 'update'])->name('notices.update');
            Route::delete('/notices/{notice}', [AdminNotice::class, 'destroy'])->name('notices.destroy');
        });

        // 繧ｳ繝ｩ繝邂｡逅・
        Route::middleware('admin.permission:content.columns')->group(function () {
            Route::get('/columns', [AdminColumn::class, 'index'])->name('columns.index');
            Route::get('/columns/create', [AdminColumn::class, 'create'])->name('columns.create');
            Route::post('/columns', [AdminColumn::class, 'store'])->name('columns.store');
            Route::get('/columns/{column}/edit', [AdminColumn::class, 'edit'])->name('columns.edit');
            Route::put('/columns/{column}', [AdminColumn::class, 'update'])->name('columns.update');
            Route::delete('/columns/{column}', [AdminColumn::class, 'destroy'])->name('columns.destroy');
        });

        // サポート問い合わせ管理
        Route::middleware('admin.permission:content.notices')->group(function () {
            Route::get('/support-inquiries', [\App\Http\Controllers\Admin\SupportInquiryController::class, 'index'])->name('support-inquiries.index');
            Route::get('/support-inquiries/{inquiry}', [\App\Http\Controllers\Admin\SupportInquiryController::class, 'show'])->name('support-inquiries.show');
            Route::post('/support-inquiries/{inquiry}/status', [\App\Http\Controllers\Admin\SupportInquiryController::class, 'updateStatus'])->name('support-inquiries.status');
            Route::post('/support-inquiries/{inquiry}/note', [\App\Http\Controllers\Admin\SupportInquiryController::class, 'updateNote'])->name('support-inquiries.note');
        });

        // ユーザー通報管理
        Route::middleware('admin.permission:content.notices')->group(function () {
            Route::get('/user-reports', [\App\Http\Controllers\Admin\UserReportController::class, 'index'])->name('user_reports.index');
            Route::post('/user-reports/{id}/status', [\App\Http\Controllers\Admin\UserReportController::class, 'updateStatus'])->name('user_reports.status')->whereNumber('id');
        });

        // 隲区ｱゅ・謖ｯ霎ｼ繧ｿ繧ｹ繧ｯ邂｡逅・
        Route::get('/tasks', [AdminTask::class, 'index'])
            ->middleware('admin.permission:dashboard.view')
            ->name('tasks.index');

        // 譛ｬ莠ｺ繝ｻ譖ｸ鬘槫ｯｩ譟ｻ
        Route::middleware('admin.permission:operations.verification')->group(function () {
            Route::get('/verification', [AdminVerification::class, 'index'])->name('verification.index');
            Route::post('/verification/cast/{document}/approve', [AdminVerification::class, 'approveCast'])->name('verification.cast.approve');
            Route::post('/verification/cast/{document}/reject', [AdminVerification::class, 'rejectCast'])->name('verification.cast.reject');
            Route::post('/verification/shopdoc/{document}/approve', [AdminVerification::class, 'approveShopDocument'])->name('verification.shopdoc.approve');
            Route::post('/verification/shopdoc/{document}/reject', [AdminVerification::class, 'rejectShopDocument'])->name('verification.shopdoc.reject');
            // 機密ファイル配信（private ディスクから直接ストリーム。Web 直アクセス禁止）
            Route::get('/verification/cast/{document}/file/{side}', [AdminVerification::class, 'viewCastFile'])
                ->where('side', 'front|back')
                ->whereNumber('document')
                ->name('verification.cast.file');
            Route::get('/verification/shopdoc/{document}/file', [AdminVerification::class, 'viewShopFile'])
                ->whereNumber('document')
                ->name('verification.shopdoc.file');
            // 保持期間ポリシーに基づく完全削除（運営の手動操作のみ。自動削除は行わない）
            Route::post('/verification/cast/{document}/purge', [AdminVerification::class, 'purgeCast'])
                ->whereNumber('document')
                ->name('verification.cast.purge');
            Route::post('/verification/shopdoc/{document}/purge', [AdminVerification::class, 'purgeShopDocument'])
                ->whereNumber('document')
                ->name('verification.shopdoc.purge');
        });

        // 蝠上＞蜷医ｏ縺帷ｮ｡逅・
        Route::middleware('admin.permission:operations.inquiries')->group(function () {
            Route::get('/inquiries', [AdminInquiry::class, 'index'])->name('inquiries.index');
            Route::get('/inquiries/{id}', [AdminInquiry::class, 'show'])->whereNumber('id')->name('inquiries.show');
        });

        // 繧｢繧ｫ繧ｦ繝ｳ繝育ｮ｡逅・ｼ磯°蝟ｶ・・
        Route::middleware('admin.permission:accounts.admins')->group(function () {
            Route::get('/admin-accounts', [AdminAccount::class, 'index'])->name('admin-accounts.index');
            Route::get('/admin-accounts/operation-log', [AdminAccount::class, 'operationLog'])->name('admin-accounts.operation-log');
            Route::get('/admin-accounts/roles/{role}/edit', [AdminAccount::class, 'editRole'])
                ->where('role', 'admin|staff')
                ->name('admin-accounts.roles.edit');
            Route::put('/admin-accounts/roles/{role}', [AdminAccount::class, 'updateRole'])
                ->where('role', 'admin|staff')
                ->name('admin-accounts.roles.update');
        });

        // 驕句霧蜿｣蠎ｧ諠・ｱ
        Route::middleware('admin.permission:operations.deposits')->group(function () {
            Route::get('/bank', [AdminBank::class, 'index'])->name('bank.index');
            Route::post('/bank', [AdminBank::class, 'store'])->name('bank.store');
        });

        // 規約管理（運営協会／利用規約／プライバシーポリシー）
        Route::middleware('admin.permission:policies.manage')->group(function () {
            Route::get('/policies/{key}', [AdminPolicy::class, 'show'])
                ->where('key', 'about|terms|privacy')
                ->name('policies.show');
            Route::get('/policies/{key}/edit', [AdminPolicy::class, 'edit'])
                ->where('key', 'about|terms|privacy')
                ->name('policies.edit');
            Route::put('/policies/{key}', [AdminPolicy::class, 'update'])
                ->where('key', 'about|terms|privacy')
                ->name('policies.update');
            Route::post('/policies/{key}/toggle-lock', [AdminPolicy::class, 'toggleLock'])
                ->where('key', 'about|terms|privacy')
                ->name('policies.toggle-lock');
        });
    });
});

/*
|--------------------------------------------------------------------------
| 1. Public & Guest Routes (LP繝ｻ隱崎ｨｼ)
|--------------------------------------------------------------------------
*/
// ### demo function and data for test ###
// Demo login: /login/demo (one-click login for cast/shop/admin roles).
// Production login routes: cast.login / shop.login / admin.login.
// Disable this block before production deploy. See CLAUDE.md.
Route::get('/login/demo', [DemoLoginController::class, 'show'])->name('login.demo');
Route::post('/login/demo', [DemoLoginController::class, 'login'])->name('login.demo.post');

// Opt-in sales fixtures. Each handler checks demo mode, environment and host before any work.
Route::get('/demo/sales', [\App\Http\Controllers\Common\SalesDemoController::class, 'show'])->name('demo.sales');
Route::post('/demo/sales/prepare', [\App\Http\Controllers\Common\SalesDemoController::class, 'prepare'])
    ->middleware('throttle:6,1')->name('demo.sales.prepare');
Route::post('/demo/sales/enter', [\App\Http\Controllers\Common\SalesDemoController::class, 'enter'])
    ->middleware('throttle:30,1')->name('demo.sales.enter');

// パスワードリセット（キャスト・店舗共通）
Route::prefix('password')->name('password.')->group(function () {
    Route::get('/forgot', [PasswordReset::class, 'showForgotForm'])->name('forgot.show');
    Route::post('/forgot', [PasswordReset::class, 'sendResetLink'])->name('forgot.post');
    Route::get('/reset', [PasswordReset::class, 'showResetForm'])->name('reset.show');
    Route::post('/reset', [PasswordReset::class, 'resetPassword'])->name('reset.post');
});

// メール認証（署名付き URL でリンクを踏むと email_verified_at が入る）
Route::prefix('auth/email')->name('auth.email.')->group(function () {
    Route::get('/verify/{type}/{id}', [EmailVerify::class, 'verify'])
        ->where(['type' => 'cast|shop', 'id' => '[cm][0-9]+'])
        ->name('verify');
    Route::post('/send', [EmailVerify::class, 'send'])
        ->middleware('throttle:6,60')  // 60 分 6 回まで（連投対策）
        ->name('send');
});
// 旧URL（/login）からの後方互換リダイレクト
Route::get('/login', fn () => redirect()->route('login.demo'));

// LINE繝ｭ繧ｰ繧､繝ｳ
Route::get('/login/line', [LineLogin::class, 'redirect'])->name('login.line.redirect');
Route::get('/login/line/callback', [LineLogin::class, 'callback'])->name('login.line.callback');

// ### demo function and data for test ###
// Mock LINE login/link routes. Only functional when DEMO_MODE=true.
// Controllers themselves check config('demo.enabled') && config('demo.mock_line').
Route::get('/login/line/mock', [\App\Http\Controllers\Auth\MockLineController::class, 'showLogin'])->name('login.line.mock');
Route::post('/login/line/mock', [\App\Http\Controllers\Auth\MockLineController::class, 'login'])->name('login.line.mock.post');

// Messaging API Webhook（LINE Login のコールバックとは別。検証は同一チャネルの Channel secret が .env と一致している必要あり）
Route::post('/line/webhook', LineWebhookController::class)->name('line.webhook');

Route::name('pages.')->group(function () {
    Route::get('/about', [PageController::class, 'about'])->name('official.about');
    Route::get('/terms', [PageController::class, 'terms'])->name('official.terms');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('official.privacy');
    Route::get('/support/column', [ColumnArticleController::class, 'index'])->name('support.column');
    Route::get('/support/column/{slug}', [ColumnArticleController::class, 'show'])->name('support.column.show');
    Route::get('/support/notices', [SupportNoticeController::class, 'index'])->name('support.notices');
    Route::get('/support/notices/{slug}', [SupportNoticeController::class, 'show'])->name('support.notices.show');
    Route::get('/support/form', [PageController::class, 'supportForm'])->name('support.form');
    Route::post('/support/form', [\App\Http\Controllers\Common\SupportInquiryController::class, 'store'])
        ->middleware('throttle:5,60')  // 1 ユーザー 60 分 5 件まで（連投スパム対策）
        ->name('support.form.submit');

    // ユーザー通報（キャスト・店舗共通）— トークルーム／プロフィール画面から呼ばれる
    Route::post('/user-report', [\App\Http\Controllers\Common\UserReportController::class, 'store'])
        ->middleware('throttle:20,60')  // 60 分 20 件まで（スパム抑止）
        ->name('user-report.store');
});

// 停止中アカウント向けランディング
Route::get('/account/suspended', [\App\Http\Controllers\Common\SuspendedController::class, 'show'])
    ->name('account.suspended');

// おしらせ既読 API（ログイン中のロール自動判定。cast/shop/admin 共通）
Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/unread-count', [CommonNotification::class, 'unreadCount'])->name('unread-count');
    Route::post('/{id}/read', [CommonNotification::class, 'markRead'])
        ->whereNumber('id')
        ->name('read');
    // Server-side mark-read + redirect for anchor clicks (ensures read_at is written before navigation).
    Route::get('/{id}/visit', [CommonNotification::class, 'visit'])
        ->whereNumber('id')
        ->name('visit');
    Route::post('/read-all', [CommonNotification::class, 'markAllRead'])->name('read-all');
});

Route::prefix('share')->name('share.')->group(function () {
    Route::get('/recruit/{id}', [CastRecruit::class, 'publicShow'])->whereNumber('id')->name('recruit.show');
    Route::get('/cast/{id}', [CastProfile::class, 'publicShow'])->name('cast.show');
});

// 險ｭ螳夂ｳｻ・亥・騾夲ｼ・
Route::prefix('setting')->name('setting.')->group(function () {
    Route::get('/notification', [SettingController::class, 'notification'])->name('notification');
    Route::post('/notification', [SettingController::class, 'updateNotification'])->name('notification.update');
    Route::get('/line/link', [LineLogin::class, 'redirectLink'])->name('line.link');
    // ### demo function and data for test ###
    // Mock LINE link (bypasses real OAuth). Handler gates on config('demo.enabled').
    Route::post('/line/mock-link', [\App\Http\Controllers\Auth\MockLineController::class, 'link'])->name('line.mock.link');
    Route::get('/account', [SettingController::class, 'account'])->name('account');
    Route::post('/account/email', [SettingController::class, 'updateEmail'])->name('account.email.update');
    Route::post('/account/password', [SettingController::class, 'updatePassword'])->name('account.password.update');
    Route::post('/account/line/unlink', [SettingController::class, 'unlinkLine'])->name('account.line.unlink');
    Route::post('/account/withdraw', [SettingController::class, 'withdraw'])->name('account.withdraw');
    // 探索拠点（現在地／パスポート）
    Route::post('/location', [\App\Http\Controllers\Common\LocationController::class, 'store'])->name('location.store');
    Route::delete('/location', [\App\Http\Controllers\Common\LocationController::class, 'destroy'])->name('location.destroy');

    // トーク定型文（キャスト／店舗ともに利用）— トーク画面のモーダルから JSON で操作
    Route::prefix('talk-templates')->name('talk-templates.')->group(function () {
        Route::get('/slots', [TalkTemplateController::class, 'slots'])->name('slots');
        Route::put('/slot/{slot}', [TalkTemplateController::class, 'saveSlot'])->whereNumber('slot')->name('slot.save');
        Route::delete('/slot/{slot}', [TalkTemplateController::class, 'resetSlot'])->whereNumber('slot')->name('slot.reset');
    });
});

// 繝励Λ繝ｳ險ｭ螳夲ｼ亥ｺ苓・蟆ら畑繝ｻ繝・Δ逕ｨ・・
Route::get('/subscription', [SettingController::class, 'subscription'])->name('subscription');
// Premiumプラン：契約（入金待ち作成）→ 振込 → 運営の入金確認で有効化。請求書/領収書DL。
Route::post('/subscription/contract', [SettingController::class, 'contractPlan'])->name('subscription.contract');
Route::post('/subscription/cancel', [SettingController::class, 'cancelPlanContract'])->name('subscription.cancel');
Route::get('/subscription/invoice', [SettingController::class, 'downloadPlanInvoice'])->name('subscription.invoice');
Route::get('/subscription/receipt', [SettingController::class, 'downloadPlanReceipt'])->name('subscription.receipt');

// 譛ｪ螳溯｣・判髱｢繝ｻ讖溯・逕ｨ・・aintenance-screen.png 繧定｡ｨ遉ｺ・・
Route::get('/maintenance', function () {
    return view('common.maintenance');
})->name('maintenance');

Route::get('/logout', [CastLogin::class, 'logout'])->name('auth.logout');

Route::middleware('signed')->group(function () {
    Route::get('/billing/invoices/{deposit}', [AdminDeposit::class, 'showSignedInvoice'])->name('billing.invoices.show');
    Route::get('/billing/invoices/{deposit}/pdf', [AdminDeposit::class, 'showSignedInvoicePdf'])->name('billing.invoices.pdf');
});

/*
|--------------------------------------------------------------------------
| PWA Push 騾夂衍 API・亥酔荳繧ｪ繝ｪ繧ｸ繝ｳ繝ｻCSRF 縺ゅｊ・・
|--------------------------------------------------------------------------
*/
Route::prefix('api/push')->name('push.')->group(function () {
    Route::get('vapid-public-key', [\App\Http\Controllers\Api\PushController::class, 'vapidPublicKey'])->name('vapid');
    Route::post('subscribe', [\App\Http\Controllers\Api\PushController::class, 'subscribe'])->name('subscribe');
    // ### demo function and data for test ###
    // Test push send to self. Gated by config('demo.enabled') && config('demo.test_push').
    Route::post('test', [\App\Http\Controllers\Api\PushController::class, 'testSend'])->name('test');
});

Route::prefix('api/favorites')->name('api.favorites.')->group(function () {
    Route::post('toggle', [\App\Http\Controllers\Api\FavoriteController::class, 'toggle'])->name('toggle');
});

Route::prefix('api/bank-lookup')->name('api.bank-lookup.')->group(function () {
    Route::get('banks', [BankLookupController::class, 'banks'])->name('banks');
    Route::get('branches', [BankLookupController::class, 'branches'])->name('branches');
});

// 住所→緯度経度のジオコーディング（保存はしない、プレビュー用）
Route::get('/api/geocoding/lookup', [\App\Http\Controllers\Common\LocationController::class, 'lookup'])
    ->name('api.geocoding.lookup');
Route::get('/api/geocoding/suggest', [\App\Http\Controllers\Common\LocationController::class, 'suggest'])
    ->name('api.geocoding.suggest');

Route::prefix('cast')->name('cast.')->group(function () {
    // Cast: production login form
    Route::get('/login', [CastLogin::class, 'showLoginForm'])->name('login');
    // Rate-limit: 5 attempts per 15 minutes per IP (brute-force protection)
    Route::post('/login', [CastLogin::class, 'login'])
        ->middleware('throttle:5,15')
        ->name('login.post');
    Route::post('/logout', [CastLogin::class, 'logout'])->name('logout');
    // Cast: registration
    Route::get('/register', [RegistrationController::class, 'showCast'])->name('register');
    Route::post('/register', [RegistrationController::class, 'storeCast'])->name('register.store');
});

Route::prefix('shop')->name('shop.')->group(function () {
    // Shop: production login form
    Route::get('/login', [ShopLogin::class, 'showLoginForm'])->name('login');
    // Rate-limit: 5 attempts per 15 minutes per IP (brute-force protection)
    Route::post('/login', [ShopLogin::class, 'login'])
        ->middleware('throttle:5,15')
        ->name('login.post');
    // Shop: registration
    Route::get('/register', [RegistrationController::class, 'showShop'])->name('register');
    Route::post('/register', [RegistrationController::class, 'storeShop'])->name('register.store');
});

/*
|--------------------------------------------------------------------------
| 2. Shop Portal (蠎苓・蟆ら畑)
|--------------------------------------------------------------------------
*/
Route::prefix('shop')->name('shop.')->middleware('shop.auth')->group(function () {

    // 新規登録直後のチュートリアル
    Route::get('/tutorial', [\App\Http\Controllers\Common\TutorialController::class, 'shopShow'])->name('tutorial');

    Route::get('/home', [DiscoveryHome::class, 'index'])->name('home');
    Route::get('/search', [ShopSearch::class, 'index'])->name('search.index');

    Route::get('/search/{tab}', fn ($tab) => redirect()->route('shop.search.index', array_merge(request()->query(), ['tab' => $tab === 'keep' ? 'keep' : 'list'])))->where('tab', 'timeline|list|keep');
    Route::post('/search-preferences', [ShopSearch::class, 'savePreferences'])->name('search-preferences.save');

    // 繝医・繧ｯ
    Route::prefix('talk')->name('talk.')->group(function () {
        Route::get('/', [TalkController::class, 'index'])->name('index');
        Route::get('/room/{id}', [TalkController::class, 'room'])->name('room');
        Route::post('/send', [TalkController::class, 'store'])->name('send');
        Route::post('/delete', [TalkController::class, 'destroy'])->name('delete');
        Route::post('/action', [TalkController::class, 'action'])->name('action');
        Route::post('/block', [TalkController::class, 'toggleBlock'])->name('block');
    });

    // 縺､縺ｪ縺後ｊ
    Route::prefix('interaction')->name('interaction.')->group(function () {
        Route::get('/', [ShopInteraction::class, 'index'])->name('index');
        Route::get('/keep', [ShopInteraction::class, 'keep'])->name('keep');
    });

    // 繧ｭ繝｣繧ｹ繝医・繝励Ο繝輔ぅ繝ｼ繝ｫ髢ｲ隕ｧ・亥ｺ苓・縺九ｉ隕九ｋ・・
    Route::get('/castprofileview/{id}', [CastProfile::class, 'show'])->name('castprofileview.show');

    // 繝励Ο繝輔ぅ繝ｼ繝ｫ・医く繝｣繧ｹ繝育畑邱ｨ髮・ｼ捏hop/profile/edit縲∝ｺ苓・逕ｨ縺ｯ store 繧ｵ繝悶ヱ繧ｹ・・
    // 店舗プロフィールの編集はオーナー専用（住所・電話・営業時間は店舗のアイデンティティ）
    Route::prefix('profile')->name('profile.')->middleware('shop.owner')->group(function () {
        Route::get('/edit', [ShopProfile::class, 'edit'])->name('edit');
        Route::post('/update', [ShopProfile::class, 'update'])->name('update');
        Route::post('/suggest-stations', [ShopProfile::class, 'suggestStations'])->name('suggest-stations');
        Route::post('/upload-image', [ShopProfile::class, 'uploadImage'])->name('upload.image');
        Route::post('/images/order', [ShopProfile::class, 'updateOrder'])->name('images.order');
        Route::delete('/image/{id}', [ShopProfile::class, 'deleteImage'])->name('image.delete');
    });

    // 笘・豎ゆｺｺ逾ｨ (Recruits)
    // 【権限分離】
    //   - hired-wage 更新／show 閲覧：スタッフも OK（面談後の日常業務）
    //   - edit / update / toggle-status：オーナー専用（給与・ボーナス・公開設定は経営判断）
    Route::prefix('recruits')->name('recruits.')->group(function () {
        Route::post('/application/hired-wage', [ShopRecruit::class, 'updateApplicationHiredWage'])->name('application-hired-wage');
        Route::middleware('shop.owner')->group(function () {
            Route::get('/preview', [ShopRecruit::class, 'preview'])->name('preview');
            Route::get('/edit', [ShopRecruit::class, 'edit'])->name('edit');
            Route::put('/update', [ShopRecruit::class, 'update'])->name('update');
            Route::post('/toggle-status', [ShopRecruit::class, 'toggleStatus'])->name('toggle-status');
            // Random auto-fill suggestion for catch_copy / manager_message
            Route::get('/suggest/{category}', [ShopRecruit::class, 'randomSuggestion'])
                ->whereIn('category', ['catch_copy', 'manager_message'])
                ->name('suggest');
        });
    });

    // 繝槭う繝壹・繧ｸ
    Route::prefix('mypage')->name('mypage.')->group(function () {
        Route::get('/', [ShopMypage::class, 'index'])->name('index');
        Route::post('/word', [ShopMypage::class, 'updateWord'])->name('word');
        // 「本日すぐ入れます」宣言（24時間で自動失効）
        Route::post('/availability', [ShopMypage::class, 'declareAvailability'])->name('availability.declare');
        Route::delete('/availability', [ShopMypage::class, 'clearAvailability'])->name('availability.clear');
        Route::post('/search-location', [ShopMypage::class, 'updateSearchLocation'])->name('search-location.update');
        Route::get('/management', [ShopRecruit::class, 'management'])->name('management');
        Route::get('/viewers', [\App\Http\Controllers\Shops\ViewerController::class, 'index'])->name('viewers.index');
        Route::get('/reviews', [ShopReview::class, 'index'])->name('review.index');
        // 許可証：閲覧はスタッフも OK（提出状況の把握）／アップロード・提出はオーナー専用
        Route::get('/documents', [ShopMypage::class, 'documents'])->name('documents.index');
        Route::get('/documents/{type}', [ShopMypage::class, 'viewLicenseDocument'])->name('documents.show')->whereIn('type', ['business', 'entertainment']);
        Route::middleware('shop.owner')->group(function () {
            Route::post('/documents/upload', [ShopMypage::class, 'uploadDocument'])->name('documents.upload');
            Route::post('/documents/request-review', [ShopMypage::class, 'requestDocumentReview'])->name('documents.request-review');
            Route::post('/documents/withdraw-review', [ShopMypage::class, 'withdrawDocumentReview'])->name('documents.withdraw-review');
            // 銀行口座・入金確認・振込操作は経営判断／お金の操作 → オーナー専用
            Route::post('/payment/bank', [ShopMypage::class, 'updateBank'])->name('payment.bank.update');
            Route::post('/deposit/approve', [ShopMypage::class, 'approveDeposit'])->name('deposit.approve');
            Route::post('/deposit/pay', [ShopMypage::class, 'payToPlatform'])->name('deposit.pay');
        });

        // スタッフ管理（1店舗に複数の店舗ログインアカウントを持たせる）
        // 閲覧のみスタッフも OK（自分の権限を把握できる）／追加・削除はオーナー専用
        // 個別の add/delete 権限は StaffController::authorizeOwner() で内部強制済み。
        // ここでは middleware で二重防御はせず、閲覧のみ許可する形にする。
        Route::prefix('staff')->name('staff.')->group(function () {
            Route::get('/', [ShopStaff::class, 'index'])->name('index');
            Route::middleware('shop.owner')->group(function () {
                Route::get('/create', [ShopStaff::class, 'create'])->name('create');
                Route::post('/', [ShopStaff::class, 'store'])->name('store');
                Route::delete('/{id}', [ShopStaff::class, 'destroy'])->name('destroy')->where('id', 'm[0-9]+');
            });
        });
    });

    // レビュー公開・非表示の切り替え（reviews 画面の JS から使用）
    Route::post('/mypage/review/update', [ShopReview::class, 'updateStatus'])->name('review.update');
    // レビューへの店舗からの返信投稿・更新・削除
    Route::post('/mypage/review/reply', [ShopReview::class, 'reply'])->name('review.reply');

    // SUPPORT 繝壹・繧ｸ・亥ｺ苓・蜷代￠・・
    Route::get('/htu', [PageController::class, 'htu'])->name('htu');

    Route::get('/column', [ColumnArticleController::class, 'index'])->name('column.index');
    Route::get('/column/{slug}', [ColumnArticleController::class, 'show'])->name('column.show');
});

/*
|--------------------------------------------------------------------------
| 3. Cast Portal (繧ｭ繝｣繧ｹ繝亥ｰら畑)
|--------------------------------------------------------------------------
*/
Route::prefix('cast')->name('cast.')->middleware('member.auth')->group(function () {
    // 新規登録直後のチュートリアル
    Route::get('/tutorial', [\App\Http\Controllers\Common\TutorialController::class, 'castShow'])->name('tutorial');

    Route::get('/home', [DiscoveryHome::class, 'index'])->name('home');
    Route::get('/profile/edit', [CastProfile::class, 'edit'])->name('profile.edit');
    Route::post('/profile/update', [CastProfile::class, 'update'])->name('profile.update');
    Route::post('/profile/personality-type', [CastProfile::class, 'updatePersonalityType'])->name('profile.personality-type');
    Route::get('/search', fn () => redirect()->route('cast.search.index', array_merge(request()->query(), ['tab' => 'list'])));
    Route::get('/search/{tab}', [CastSearch::class, 'index'])->name('search.index')->where('tab', 'search|ai|timeline|list|keep');
    Route::post('/search-preferences', [CastSearch::class, 'savePreferences'])->name('search-preferences.save');
    Route::post('/search/ai-chat', [CastAiChat::class, 'respond'])->name('search.ai-chat');
    Route::get('/shopprofiles/{id}', [CastRecruit::class, 'show'])->name('shopprofile.show');
    // 旧URL互換：/cast/shopprofileview/{id} → /cast/shopprofiles/{id}（shop.castprofileview.show との対称性確保）
    Route::get('/shopprofileview/{id}', fn ($id) => redirect()->route('cast.shopprofile.show', ['id' => $id]))->name('shopprofileview.show');

    Route::get('/interaction', [ShopInteraction::class, 'index'])->name('interaction.index');
    Route::get('/interaction/keep', [ShopInteraction::class, 'keep'])->name('interaction.keep');
    Route::get('/mypage', [CastMypage::class, 'index'])->name('mypage.index');
    Route::post('/mypage/word', [CastMypage::class, 'updateWord'])->name('mypage.word');
    Route::post('/mypage/search-location', [CastMypage::class, 'updateSearchLocation'])->name('mypage.search-location.update');
    // 「今すぐ入れる」宣言（店舗ホームの Tier A 判定に用いる）
    Route::post('/mypage/availability', [CastMypage::class, 'declareAvailability'])->name('mypage.availability.declare');
    Route::delete('/mypage/availability', [CastMypage::class, 'clearAvailability'])->name('mypage.availability.clear');
    Route::get('/mypage/management', [CastMypage::class, 'employment'])->name('mypage.management');
    Route::get('/mypage/reviews', [CastMypage::class, 'reviews'])->name('mypage.reviews');
    Route::post('/mypage/payment/bank', [CastMypage::class, 'updateBank'])->name('mypage.payment.bank.update');
    // Identity verification: extracted to dedicated Casts\IdentityController (2026-08-02)
    Route::get('/mypage/identity', [\App\Http\Controllers\Casts\IdentityController::class, 'identity'])->name('mypage.identity');
    Route::post('/mypage/identity/remind', [\App\Http\Controllers\Casts\IdentityController::class, 'identityRemind'])->name('mypage.identity.remind');
    Route::post('/mypage/identity/upload', [\App\Http\Controllers\Casts\IdentityController::class, 'upload'])->name('mypage.identity.upload');
    Route::post('/mypage/identity/submit', [\App\Http\Controllers\Casts\IdentityController::class, 'submitForReview'])->name('mypage.identity.submit');
    Route::post('/mypage/images/upload', [CastMypage::class, 'uploadImage'])->name('mypage.images.upload');
    Route::post('/mypage/images/order', [CastMypage::class, 'updateImageOrder'])->name('mypage.images.order');
    Route::delete('/mypage/images/{id}', [CastMypage::class, 'deleteImage'])->name('mypage.images.delete');
    Route::post('/mypage/deposit/request', [CastMypage::class, 'requestDeposit'])->name('mypage.deposit.request');
    Route::post('/mypage/deposit/review', [CastMypage::class, 'postReview'])->name('mypage.deposit.review');
    Route::get('/mypage/deposit/request-target', [CastMypage::class, 'getDepositRequestTarget'])->name('mypage.deposit.request-target');
    Route::post('/mypage/deposit/confirm', [CastMypage::class, 'confirmDeposit'])->name('mypage.deposit.confirm');

    Route::prefix('talk')->name('talk.')->group(function () {
        Route::get('/', [TalkController::class, 'index'])->name('index');
        Route::get('/room/{id}', [TalkController::class, 'room'])->name('room');
        Route::post('/send', [TalkController::class, 'store'])->name('send');
        Route::post('/delete', [TalkController::class, 'destroy'])->name('delete');
        Route::post('/action', [TalkController::class, 'action'])->name('action');
        Route::post('/block', [TalkController::class, 'toggleBlock'])->name('block');
    });

    // SUPPORT 繝壹・繧ｸ・医く繝｣繧ｹ繝亥髄縺托ｼ・
    Route::get('/htu', [PageController::class, 'htu'])->name('htu');

    Route::get('/column', [ColumnArticleController::class, 'index'])->name('column.index');
    Route::get('/column/{slug}', [ColumnArticleController::class, 'show'])->name('column.show');
});

/* 蜀・Κ繧ｭ繝｣繝・す繝･繧ｯ繝ｪ繧｢逕ｨ */
Route::get('/clear-route', function() {
    \Artisan::call('route:clear');
    return "Route cache cleared!";
});
