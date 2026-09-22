@extends('layouts.app-v2')

@php
    $selectedRole = old('role', $roleGroups[0]['key'] ?? 'cast');
    $demoEmails = [
        'cast' => 'cast@demo.com',
        'shop' => 'shop@demo.com',
        'admin' => 'admin@demo.com',
    ];
    $roleLabels = [
        'cast'  => 'キャスト',
        'shop'  => '店舗',
        'admin' => '運営',
    ];
@endphp

@section('title', 'ログイン（デモ）')
@section('body-class', 'page-demo-login')

@push('styles')
<style>
    /* ロール選択チップ：立体感を排したフラット表現（2026-08-09）。
       Tailwind の shadow-badge-3d / shadow-input-dark と bg-gradient を撤去し、
       ボーダーとアクセント淡色のみで active/inactive を区別する。 */
    .demo-role-chip {
        background: transparent;
        border: 1px solid rgba(168, 85, 247, 0.30);
        color: rgba(255, 255, 255, 0.60);
        box-shadow: none;
    }
    .demo-role-chip:hover {
        border-color: rgba(168, 85, 247, 0.55);
        color: rgba(255, 255, 255, 0.85);
    }
    .demo-role-chip[data-active="true"] {
        background: rgba(168, 85, 247, 0.14);
        border-color: rgba(168, 85, 247, 0.75);
        color: #d8b4fe;
        box-shadow: none;
    }
    /* ライトテーマ時の色調も揃える */
    body.theme-light .demo-role-chip {
        border-color: rgba(124, 58, 237, 0.30);
        color: rgba(36, 31, 51, 0.55);
    }
    body.theme-light .demo-role-chip:hover {
        border-color: rgba(124, 58, 237, 0.55);
        color: rgba(36, 31, 51, 0.90);
    }
    body.theme-light .demo-role-chip[data-active="true"] {
        background: rgba(196, 181, 253, 0.18);
        border-color: rgba(124, 58, 237, 0.60);
        color: #7c3aed;
    }
</style>
@endpush

@section('content')
<div data-theme="amethyst" class="bg-base flex flex-col justify-center items-center min-h-[100dvh] relative overflow-hidden">

    {{-- 背景：薄いライラックグロー（テーマアクセントで自動追従） --}}
    <div class="absolute inset-0 z-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute top-[-10%] left-[-10%] w-[60%] h-[50%] bg-accent rounded-full blur-[140px] opacity-10"></div>
        <div class="absolute bottom-[-15%] right-[-15%] w-[55%] h-[45%] bg-accent-grad-to rounded-full blur-[140px] opacity-10"></div>
        <div class="absolute top-[35%] left-[20%] w-[40%] h-[35%] bg-accent-grad-from rounded-full blur-[160px] opacity-[0.06]"></div>
    </div>

    {{-- メインコンテンツ --}}
    <div class="relative z-10 w-full max-w-[430px] px-5 py-8">

        {{-- タイトルロゴ --}}
        <div class="text-center mb-6">
            <h1 class="app-title text-[34px] font-extrabold tracking-[0.18em] text-transparent bg-clip-text bg-gradient-to-r from-accent-grad-from to-accent-grad-to mb-1">
                MISECHOKU
            </h1>
            <p class="text-[11px] tracking-[0.32em] text-text-sub uppercase">Demo Login</p>
        </div>

        {{-- フラッシュ --}}
        @if (session('message'))
            <div class="mb-4 px-4 py-3 rounded-panel border border-line-accent/30 bg-surface-from/80 backdrop-blur-md text-accent-text text-[12.5px] leading-relaxed">
                {{ session('message') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 px-4 py-3 rounded-panel border border-rose-400/30 bg-rose-900/30 backdrop-blur-md text-rose-100 text-[12.5px] leading-relaxed">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if (\App\Support\SalesDemoAccess::enabled(request()->getHost()))
            <div class="mb-5">
                <x-ui.button as="a" :href="route('demo.sales')" size="lg" class="w-full">営業デモをまとめて準備する</x-ui.button>
            </div>
        @endif

        {{-- ログインカード（Glassmorphism + 3D） --}}
        <div class="w-full bg-surface-from/90 backdrop-blur-xl border border-line-accent/30 rounded-card shadow-card-3d overflow-hidden">

            {{-- タブ切り替え --}}
            <div class="grid grid-cols-2 border-b border-line-accent/20" role="tablist" aria-label="認証切り替え">
                <button type="button"
                        class="relative py-4 text-[12px] font-bold tracking-[0.18em] uppercase text-text-sub data-[active=true]:text-accent-text transition-colors"
                        data-auth-tab="login" data-active="true">
                    ログイン
                    <span class="auth-tab-indicator absolute left-1/2 -translate-x-1/2 bottom-0 h-[3px] w-1/2 bg-gradient-to-r from-accent-grad-from to-accent-grad-to rounded-t-full opacity-100 transition-opacity"></span>
                </button>
                <button type="button"
                        class="relative py-4 text-[12px] font-bold tracking-[0.18em] uppercase text-text-sub data-[active=true]:text-accent-text transition-colors"
                        data-auth-tab="register" data-active="false">
                    新規登録
                    <span class="auth-tab-indicator absolute left-1/2 -translate-x-1/2 bottom-0 h-[3px] w-1/2 bg-gradient-to-r from-accent-grad-from to-accent-grad-to rounded-t-full opacity-0 transition-opacity"></span>
                </button>
            </div>

            {{-- パネル：ログイン --}}
            <div class="px-5 py-6" data-auth-panel="login">
                <form method="POST" action="{{ route('login.demo.post') }}" id="demo-login-form" class="flex flex-col gap-4">
                    @csrf
                    <input type="hidden" name="role" id="demo-role-input" value="{{ $selectedRole }}">
                    <input type="hidden" name="account_id" id="demo-account-input" value="">

                    {{-- ロール選択：キャスト / 店舗 / 運営 --}}
                    <div class="grid grid-cols-3 gap-2" role="tablist" aria-label="ログインロール切り替え">
                        @foreach ($roleGroups as $group)
                            @php $active = $selectedRole === $group['key']; @endphp
                            {{-- Flat role chip: no shadow / no gradient (2026-08-09).
                                 Active state = accent border + accent tinted background. Inactive = plain border. --}}
                            <button type="button"
                                    data-role-tab="{{ $group['key'] }}"
                                    data-demo-email="{{ $demoEmails[$group['key']] ?? 'demo@misechoku.jp' }}"
                                    data-role-label="{{ $group['label'] }}"
                                    class="demo-role-chip relative inline-flex items-center justify-center min-h-[42px] px-3 rounded-panel text-[12px] font-bold tracking-wider transition-colors duration-150"
                                    data-active="{{ $active ? 'true' : 'false' }}">
                                {{ $group['label'] }}
                            </button>
                        @endforeach
                    </div>

                    {{-- LINE ログイン --}}
                    @if (in_array($selectedRole, ['cast', 'shop']))
                        <a id="demo-login-line-btn"
                           href="{{ route('login.line.redirect', ['role' => $selectedRole]) }}"
                           data-base-url="{{ route('login.line.redirect') }}"
                           class="inline-flex items-center justify-center gap-2 w-full min-h-[52px] rounded-panel font-bold text-[14px] tracking-wider text-white bg-gradient-to-r from-[#06C755] to-[#05A546] shadow-btn-3d active:translate-y-1 active:shadow-btn-3d-active transition-all duration-150">
                            <i class="fa-brands fa-line text-[18px]"></i>
                            <span>LINE でログイン</span>
                        </a>
                    @endif

                    {{-- ### demo function and data for test ###
                         DEMO_MODE 有効時のみ表示: モック LINE ログイン（本番 OAuth 不要） --}}
                    @if (config('demo.enabled') && config('demo.mock_line') && in_array($selectedRole, ['cast', 'shop']))
                        <a href="{{ route('login.line.mock', ['role' => $selectedRole]) }}"
                           class="inline-flex items-center justify-center gap-2 w-full min-h-[44px] rounded-panel font-bold text-[12px] tracking-wider text-text-main bg-accent/10 border border-line-accent/40">
                            <i class="fa-solid fa-flask-vial"></i>
                            <span>[デモ] モック LINE でログイン</span>
                        </a>
                    @endif

                    {{-- OR divider --}}
                    <div class="flex items-center gap-3 my-1">
                        <span class="flex-1 h-px bg-line-accent/20"></span>
                        <span class="text-[10px] tracking-widest text-text-sub">OR</span>
                        <span class="flex-1 h-px bg-line-accent/20"></span>
                    </div>

                    {{-- フィールド：ログイン種別（select） --}}
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-bold tracking-wider text-accent-text">ログイン種別</span>
                        <div class="relative">
                            @foreach ($roleGroups as $group)
                                <select data-account-select="{{ $group['key'] }}"
                                        class="demo-login-select appearance-none w-full bg-base text-text-main border border-line/40 rounded-panel py-3.5 px-4 pr-10 shadow-input-dark focus:border-line-accent/60 focus:outline-none transition-colors text-[14px]"
                                        {{ $selectedRole === $group['key'] ? '' : 'style="display:none"' }}>
                                    @foreach ($group['accounts'] as $account)
                                        <option value="{{ $account['id'] }}"
                                                @selected(old('role', $selectedRole) === $group['key'] && old('account_id', $group['accounts'][0]['id'] ?? null) == $account['id'])>
                                            {{ $account['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            @endforeach
                            <i class="ph-bold ph-caret-down absolute right-4 top-1/2 -translate-y-1/2 text-accent-text text-[16px] pointer-events-none"></i>
                        </div>
                    </label>

                    {{-- フィールド：メール --}}
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-bold tracking-wider text-accent-text">メールアドレス</span>
                        <input type="email" id="demo-email-display" value="" readonly
                               class="w-full bg-base text-text-main border border-line/40 rounded-panel py-3.5 px-4 shadow-input-dark focus:border-line-accent/60 focus:outline-none transition-colors text-[14px]">
                    </label>

                    {{-- フィールド：パスワード --}}
                    <label class="flex flex-col gap-1.5">
                        <span class="text-[11px] font-bold tracking-wider text-accent-text">パスワード</span>
                        <input type="password" value="demo_password_123" readonly
                               class="w-full bg-base text-text-main border border-line/40 rounded-panel py-3.5 px-4 shadow-input-dark focus:border-line-accent/60 focus:outline-none transition-colors text-[14px]">
                    </label>

                    <p class="text-[11px] text-text-sub leading-relaxed">
                        アカウントを選んでログインできます。
                    </p>

                    {{-- 通常ログインボタン --}}
                    <button type="submit" name="auth_channel" value="standard"
                            class="inline-flex items-center justify-center w-full min-h-[54px] rounded-panel font-bold text-[15px] tracking-wider text-on-accent bg-gradient-to-r from-accent-grad-from to-accent-grad-to shadow-btn-3d active:translate-y-1 active:shadow-btn-3d-active transition-all duration-150 mt-2">
                        ログイン
                    </button>
                </form>
            </div>

            {{-- パネル：新規登録 --}}
            <div class="px-5 py-6 hidden" data-auth-panel="register">
                <h2 class="text-[13px] font-bold tracking-[0.18em] uppercase text-accent-text mb-4 text-center">登録種別を選択</h2>
                <div class="flex flex-col gap-3">
                    <a href="{{ route('cast.register') }}"
                       class="inline-flex items-center justify-center min-h-[52px] rounded-panel border border-line-accent/30 bg-base text-text-main text-[14px] font-bold tracking-wider shadow-input-dark hover:border-line-accent/60 hover:text-accent-text transition-all">
                        <i class="ph-fill ph-user mr-2 text-accent-text"></i> キャスト登録
                    </a>
                    <a href="{{ route('shop.register') }}"
                       class="inline-flex items-center justify-center min-h-[52px] rounded-panel border border-line-accent/30 bg-base text-text-main text-[14px] font-bold tracking-wider shadow-input-dark hover:border-line-accent/60 hover:text-accent-text transition-all">
                        <i class="ph-fill ph-storefront mr-2 text-accent-text"></i> 店舗登録
                    </a>
                    {{-- 運営は新規登録の対象外（ログインタブから認証） --}}
                </div>
            </div>
        </div>

        {{-- フッターリンク --}}
        <div class="flex items-center justify-center gap-3 mt-6 text-[11px] text-text-sub">
            <a href="{{ route('pages.official.terms') }}" class="hover:text-accent-text transition-colors">利用規約</a>
            <span class="opacity-40">|</span>
            <a href="{{ route('pages.official.privacy') }}" class="hover:text-accent-text transition-colors">プライバシーポリシー</a>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('demo-login-form');
    if (!loginForm) return;

    const roleInput     = document.getElementById('demo-role-input');
    const accountInput  = document.getElementById('demo-account-input');
    const emailDisplay  = document.getElementById('demo-email-display');
    const roleButtons   = Array.from(document.querySelectorAll('[data-role-tab]'));
    const accountSelects= Array.from(document.querySelectorAll('[data-account-select]'));
    const tabButtons    = Array.from(document.querySelectorAll('[data-auth-tab]'));
    const tabPanels     = Array.from(document.querySelectorAll('[data-auth-panel]'));
    const lineBtn       = document.getElementById('demo-login-line-btn');

    function syncAccountInput(role) {
        const activeSelect      = document.querySelector('[data-account-select="' + role + '"]');
        const activeRoleButton  = document.querySelector('[data-role-tab="' + role + '"]');
        if (!activeSelect || !activeRoleButton) return;
        accountInput.value = activeSelect.value;
        emailDisplay.value = activeRoleButton.dataset.demoEmail || 'demo@misechoku.jp';
    }

    function switchRole(role) {
        roleInput.value = role;

        roleButtons.forEach(function (btn) {
            btn.dataset.active = (btn.dataset.roleTab === role) ? 'true' : 'false';
        });

        accountSelects.forEach(function (sel) {
            sel.style.display = (sel.dataset.accountSelect === role) ? '' : 'none';
        });

        if (lineBtn) {
            if (role === 'cast' || role === 'shop') {
                lineBtn.style.display = '';
                lineBtn.href = lineBtn.getAttribute('data-base-url') + '?role=' + encodeURIComponent(role);
            } else {
                lineBtn.style.display = 'none';
            }
        }

        syncAccountInput(role);
    }

    roleButtons.forEach(function (btn) {
        btn.addEventListener('click', function () { switchRole(btn.dataset.roleTab); });
    });

    accountSelects.forEach(function (sel) {
        sel.addEventListener('change', function () {
            if (sel.dataset.accountSelect === roleInput.value) {
                accountInput.value = sel.value;
            }
        });
    });

    tabButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const target = btn.dataset.authTab;
            tabButtons.forEach(function (b) {
                const active = (b === btn);
                b.dataset.active = active ? 'true' : 'false';
                const indicator = b.querySelector('.auth-tab-indicator');
                if (indicator) indicator.style.opacity = active ? '1' : '0';
            });
            tabPanels.forEach(function (panel) {
                if (panel.dataset.authPanel === target) {
                    panel.classList.remove('hidden');
                } else {
                    panel.classList.add('hidden');
                }
            });
        });
    });

    switchRole(roleInput.value || '{{ $selectedRole }}');
});
</script>
@endpush
