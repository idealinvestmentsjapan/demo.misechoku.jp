<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * 管理者ログイン画面
     *
     * system_accounts を用いた管理者ログイン画面。
     */
    public function showLoginForm()
    {
        return view('admin.auth.login');
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

