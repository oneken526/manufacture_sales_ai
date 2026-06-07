<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 【機能概要】: 認証済みユーザーのロールが許可リストに含まれない場合にHTTP403で拒否するミドルウェア
 * 【改善内容】: ロール名⇔UserRole Enumの変換を UserRole::fromRouteKey() に委譲し、
 *              本クラス内に重複したマッピング定義を持たない形へ整理した
 * 【実装方針】: ルート定義側で `role:admin` のように許可ロール名を可変長引数で受け取り、
 *              現在のユーザーのロールと照合するシンプルな実装とする
 * 【テスト対応】: tests/Feature/Middleware/EnsureUserHasRoleTest.php の3テストを通すための実装
 * 🔵 信頼性レベル: TASK-0003.md 実装詳細2「許可ロール一覧に含まれない場合のみ拒否する」に直接基づく
 */
class EnsureUserHasRole
{
    /**
     * 【処理内容】: リクエストユーザーのロールが許可ロール一覧に含まれるか判定し、
     *              含まれない場合は403を返却、含まれる場合は後続処理へ進める
     * 【保守性】: ロール名⇔Enumの対応はUserRole側に集約済みのため、ロール追加時もこのクラスは変更不要
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        // 【入力値変換】: ルート定義の許可ロール名（例: 'admin', 'accounting'）をUserRole Enumへ変換する
        $allowedRoles = array_map(
            fn (string $role): ?UserRole => UserRole::fromRouteKey($role),
            $roles
        );

        if ($user === null || ! in_array($user->role, $allowedRoles, true)) {
            // 【エラー処理】: 許可ロール外のユーザーはコントローラ処理に到達させずHTTP403で拒否する
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
