<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 運営ログインの URL パス（予測不可能化）
    |--------------------------------------------------------------------------
    |
    | /admin/login のような予測可能な URL を避けるため、運営用ログイン画面は
    | 意図的に見つけにくいパス配下に置く。デプロイ時は .env の
    | ADMIN_LOGIN_PATH を各環境固有のランダム文字列に差し替えること。
    |
    | 例: staff-9k3xq8-portal, ops-a7k92pf-console など
    |
    | 名前付きルート `admin.login` / `admin.login.post` / `admin.logout` は
    | 変わらないため、`route('admin.login')` を使うコードは何も変更不要。
    |
    */
    'login_path' => env('ADMIN_LOGIN_PATH', 'staff-portal-a7k92pf'),

];
