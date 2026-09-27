<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * 管理者ログイン画面
     *
     * cast / shop と同じ common.role-login ビューを再利用し、
     * ログイン UI をロール間で統一する。運営ログインは
     * config('admin.login_path') 配下の obscure URL に配置される。
     */
    public function showLoginForm()
    {
        return view('common.role-login', [
            'role'          => 'admin',
            'title'         => '運営ログイン',
            'bodyClass'     => 'page-auth-login page-auth-login-admin',
            'formAction'    => route('admin.login.post'),
            // 運営は自己登録できないため未使用だが、ビュー側の未定義参照を避けるために埋めておく
            'registerUrl'   => route('login.demo'),
            'registerLabel' => '',
        ]);
    }

    /**
     * 管理者ログイン処理
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (!auth()->guard('admin')->attempt([
            'email' => (string) $request->input('email'),
            'password' => (string) $request->input('password'),
            'is_active' => true,
        ])) {
            return back()
                ->withErrors(['email' => 'メールアドレスまたはパスワードが正しくありません。'])
                ->withInput();
        }

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')
            ->with('status', '管理者としてログインしました。');
    }

    /**
     * 管理者ログアウト
     */
    public function logout(Request $request)
    {
        auth()->guard('admin')->logout();
        // 同じブラウザで利用中のキャスト／店舗ガードの認証状態は保持する。
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login')->with('status', 'ログアウトしました。');
    }
}

