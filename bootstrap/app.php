<?php

use App\Exceptions\PdfGenerationException;
use App\Http\Middleware\EnsureUserHasRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // 【ミドルウェアエイリアス登録】: ルート定義で `role:admin` のように使用できるようにする
        // 🔵 信頼性レベル: TASK-0003.md・auth-rbac-red-phase.mdの要求事項に直接基づく
        $middleware->alias([
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );

        // 【機能概要】: PdfGenerationException発生時に画面へ再試行を促すフラッシュメッセージを表示する共通基盤
        // 【実装方針】: 後続タスク（見積/納品/請求のPDF出力Controller）が個別に例外捕捉を実装しなくても、
        //               ここで一元的にレンダリングし、`session('pdf_error')`をBladeビュー側で表示できるようにする
        // 【テスト対応】: タスク詳細実装詳細3「Controller層でPdfGenerationExceptionを捕捉し、
        //               画面にフラッシュメッセージとして表示する仕組みの基盤（共通の例外ハンドラ）を用意する」
        // 🟡 信頼性レベル: EDGE-003・タスク詳細の指示から、Laravel標準のレンダリングフックを用いた基盤として妥当に推測
        $exceptions->render(function (PdfGenerationException $e, Request $request) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

            // 【画面表示】: 直前の画面へ戻し、再試行を促すメッセージをセッションフラッシュへ格納する
            return back()->with('pdf_error', $e->getMessage());
        });
    })->create();
