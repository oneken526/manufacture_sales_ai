<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * 【機能概要】: PDF生成処理（Bladeレンダリング・mPDF変換・ファイル保存）に失敗した際にスローする独自例外
 * 【実装方針】: EDGE-003（PDF生成失敗時はエラーメッセージを表示し再試行を促す）に対応するため、
 *               ユーザー向けの再試行メッセージを固定文言として保持する専用例外クラスとして実装する
 * 【テスト対応】: TC6, TC7, TC8（PdfGenerationExceptionがスローされ、メッセージに「再度お試しください」を含むこと）
 * 🟡 信頼性レベル: requirements.md EDGE-003・タスクファイル実装詳細3に基づく（メッセージ文言はタスク詳細の例示を採用）
 */
class PdfGenerationException extends RuntimeException
{
    /**
     * 【機能概要】: ユーザー向けの再試行を促すメッセージを固定文言として持つ例外を生成する
     * 【実装方針】: タスクファイルに明記された「PDFの生成に失敗しました。しばらく経ってから再度お試しください」を
     *               メッセージとして採用し、元例外（mPDF・Blade等の例外）をchainして保持する
     * 【テスト対応】: expectExceptionMessage('再度お試しください') を満たすため
     * 🟡 信頼性レベル: タスクファイル実装詳細3の例示文言に基づく
     *
     * @param string $view 生成に失敗したBladeビュー名（ログ出力用に呼び出し元で利用する）
     * @param Throwable|null $previous 原因となった例外（フォント読み込み失敗・レンダリングエラー等）
     */
    public function __construct(string $view, ?Throwable $previous = null)
    {
        // 【メッセージ固定化】: ユーザーに内部実装の詳細（スタックトレース等）を見せず、
        // 次に取るべき行動（再試行）が明確に伝わる文言に統一する
        parent::__construct(
            'PDFの生成に失敗しました。しばらく経ってから再度お試しください。',
            0,
            $previous,
        );
    }
}
