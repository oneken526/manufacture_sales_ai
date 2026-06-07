<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // 【ロール別リダイレクト】: ログイン直後の遷移先をUser::roleに応じて分岐させる
        // 【実装方針】: intended()による「元々アクセスしようとしていたURL」を優先しつつ、
        //              フォールバック先をロールごとの専用画面に変更する
        // 🔵 信頼性レベル: TASK-0003.md 実装詳細1「admin→管理ダッシュボード」等のロール別リダイレクト要件に直接基づく
        return redirect()->intended($this->intendedRouteFor($request->user()->role));
    }

    /**
     * 【機能概要】: ロールに応じたログイン後の遷移先ルートのURLを決定する
     * 【改善内容】: ロール→ルート名の対応表をUserRole::routeKey()に委譲し、
     *              本クラスとEnsureUserHasRoleミドルウェアでの重複定義を解消した
     * 【保守性】: ルート名は `{routeKey}.dashboard` の規則に統一されているため、
     *            ロール追加時もルート定義（routes/web.php）と本メソッドのみの対応で済む
     * 🔵 信頼性レベル: TASK-0003.md 実装詳細1の4ロール別リダイレクト先の対応表に直接基づく
     */
    private function intendedRouteFor(UserRole $role): string
    {
        return route("{$role->routeKey()}.dashboard", absolute: false);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
