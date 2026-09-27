<?php

namespace Tests\Support;

final class SmokeRouteMatrix
{
    public static function publicPages(): array
    {
        return [
            'demo login' => ['login.demo', [], 200],
            'about' => ['pages.official.about', [], 200],
            'terms' => ['pages.official.terms', [], 200],
            'privacy' => ['pages.official.privacy', [], 200],
            'support column' => ['pages.support.column', [], 200],
            'support form' => ['pages.support.form', [], 200],
            'setting notification' => ['setting.notification', [], 200],
            'setting account' => ['setting.account', [], 200],
            'subscription' => ['subscription', [], 200],
            'maintenance' => ['maintenance', [], 200],
        ];
    }

    public static function adminPages(): array
    {
        return [
            'admin dashboard' => ['admin.dashboard', [], 200],
            // deposits.index redirects to confirmations; hit the two real screens directly
            'admin deposits confirmations' => ['admin.deposits.confirmations', [], 200],
            'admin deposits transfers' => ['admin.deposits.transfers', [], 200],
            'admin sales' => ['admin.sales.index', [], 200],
            'admin masters' => ['admin.masters.index', [], 200],
            'admin shops' => ['admin.shops.index', [], 200],
            'admin casts' => ['admin.casts.index', [], 200],
            'admin ngwords' => ['admin.ngwords.index', [], 200],
            'admin notices' => ['admin.notices.index', [], 200],
            'admin columns' => ['admin.columns.index', [], 200],
            'admin tasks' => ['admin.tasks.index', [], 200],
            'admin verification' => ['admin.verification.index', [], 200],
            'admin inquiries' => ['admin.support-inquiries.index', [], 200],
            'admin accounts' => ['admin.admin-accounts.index', [], 200],
            'admin bank' => ['admin.bank.index', [], 200],
        ];
    }

    public static function shopPages(): array
    {
        return [
            'shop home' => ['shop.home', [], 200],
            'shop search' => ['shop.search.index', [], 200],
            'shop search keep' => ['shop.search.index', ['tab' => 'keep'], 200],
            'shop interaction index (redirects to search keep tab)' => ['shop.interaction.index', [], 302],
            'shop interaction keep (redirects to search keep tab)' => ['shop.interaction.keep', [], 302],
            'shop profile edit' => ['shop.profile.edit', [], 200],
            'shop recruits edit' => ['shop.recruits.edit', [], 200],
            'shop mypage index' => ['shop.mypage.index', [], 200],
            'shop mypage management' => ['shop.mypage.management', [], 200],
            'shop mypage reviews' => ['shop.mypage.review.index', [], 200],
            'shop talk index' => ['shop.talk.index', [], 200],
        ];
    }

    public static function castPages(): array
    {
        return [
            'cast home' => ['cast.home', [], 200],
            'cast search' => ['cast.search.index', ['tab' => 'list'], 200],
            'cast search ai' => ['cast.search.index', ['tab' => 'ai'], 200],
            'cast search keep' => ['cast.search.index', ['tab' => 'keep'], 200],
            'cast profile edit' => ['cast.profile.edit', [], 200],
            'cast mypage index' => ['cast.mypage.index', [], 200],
            'cast mypage management' => ['cast.mypage.management', [], 200],
            'cast mypage reviews' => ['cast.mypage.reviews', [], 200],
            'cast mypage identity' => ['cast.mypage.identity', [], 200],
            'cast interaction index (redirects to search keep tab)' => ['cast.interaction.index', [], 302],
            'cast talk index' => ['cast.talk.index', [], 200],
        ];
    }
}
