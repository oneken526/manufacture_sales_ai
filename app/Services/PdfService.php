<?php

namespace App\Services;

use App\Exceptions\PdfGenerationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Throwable;

/**
 * 【機能概要】: 見積書・納品書・請求書の3帳票で共通利用するPDF生成基盤（mPDFラッパー）
 * 【実装方針】: Bladeビュー（テンプレート名＋データ配列）をHTML化し、mPDFでPDFバイナリへ変換する。
 *               帳票種別に依存しない汎用インターフェースとし、帳票固有のレイアウト・データ整形は
 *               各帳票テンプレート側（後続タスク）に委ねる（責務分離）。
 *               Mpdfインスタンスはコンテナ経由（app(Mpdf::class)）で解決することで、
 *               将来的なキュー非同期化や単体テストでのモック差し替えを容易にする。
 * 【テスト対応】: TC1, TC2, TC3, TC4, TC5, TC6, TC7, TC8, TC9, TC10（pdf-service-testcases.md）
 * 🔵 信頼性レベル: architecture.md「Services/PdfService.php # mPDFラッパー」、dataflow.md「PdfService(mPDF)」に基づく
 */
class PdfService
{
    /**
     * 【機能概要】: BladeビューからPDFをダウンロードレスポンスとして出力する
     * 【改善内容】: 旧実装では`fromView()`で個別にMpdfを組み立てておりエラーハンドリングが無かったため、
     *               `generateFromView()`に処理を委譲する形に統一した。これによりレンダリング・PDF変換の
     *               重複ロジックを排除しつつ、ダウンロード時もEDGE-003のエラーハンドリング
     *               （PdfGenerationException・ログ記録・再試行メッセージ）が一貫して適用されるようになった。
     * 【設計方針】: Controllerからは本メソッドを呼ぶだけでPDFダウンロードレスポンスを得られるようにし、
     *               生成・変換・エラー処理の詳細はPdfService内に閉じ込める（責務分離）
     * 【保守性】: PDF生成ロジックの変更点は`generateFromView()`一箇所に集約されるため、保守時の修正箇所が明確になる
     * 🔵 信頼性レベル: タスクファイル実装詳細1「downloadメソッドも提供し、Controllerから簡潔に呼び出せるようにする」に基づく
     *
     * @param string $view Bladeビュー名
     * @param array<string, mixed> $data ビューに渡すデータ配列
     * @param string $filename ダウンロード時のファイル名（Content-Dispositionヘッダーに使用）
     * @return \Illuminate\Http\Response PDFダウンロードレスポンス
     *
     * @throws PdfGenerationException PDF生成に失敗した場合（generateFromView()から伝播）
     */
    public function download(string $view, array $data, string $filename)
    {
        // 【処理委譲】: バイナリ生成・例外ハンドリングは generateFromView() に一元化されているため、
        // 本メソッドはHTTPレスポンスへの変換のみに専念する
        $binary = $this->generateFromView($view, $data, $filename);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * 【機能概要】: Bladeビュー（テンプレート名＋データ配列）からPDFバイナリを生成して返す
     * 【実装方針】: view()->render()でHTML化し、mPDFのOutput('', 'S')でPDFバイナリ文字列を取得する。
     *               レンダリング・PDF変換のいずれの段階で例外が発生してもPdfGenerationExceptionにラップし、
     *               運用調査に必要な情報（テンプレート名・例外内容）をログへ記録する（EDGE-003対応）。
     * 【テスト対応】: TC1（PDFバイナリ生成）, TC2（日本語埋め込み）, TC6/TC7/TC8（例外ハンドリング）, TC9（空データ）
     * 🔵 信頼性レベル: タスクファイル実装詳細1のメソッド定義「generateFromView(string $view, array $data, ?string $filename = null): string」に直接基づく
     *
     * @param string $view Bladeビュー名（例: 'pdf.quotation'）
     * @param array<string, mixed> $data ビューに渡すデータ配列
     * @param string|null $filename mPDFのOutputに渡すファイル名（省略可）
     * @return string PDFバイナリ文字列（'%PDF-'で始まるバイト列）
     *
     * @throws PdfGenerationException PDF生成に失敗した場合（テンプレート不在・レンダリング失敗・mPDF内部エラー等）
     */
    public function generateFromView(string $view, array $data = [], ?string $filename = null): string
    {
        try {
            // 【処理内容】: Bladeビューをレンダリングし、HTML文字列に変換する（存在しないビュー名・未定義変数アクセスはここで例外化される）
            $html = view($view, $data)->render();

            // 【処理内容】: コンテナ経由でMpdfを解決し（config/mpdf.phpの日本語フォント設定が適用される）、HTMLを書き込んでPDF化する
            $mpdf = app(Mpdf::class);
            $mpdf->WriteHTML($html);

            // 【結果返却】: 'S'を指定し、ファイル出力やHTTP出力を行わずPDFバイナリ文字列として取得する
            return $mpdf->Output($filename ?? '', 'S');
        } catch (Throwable $e) {
            // 【エラー捕捉】: テンプレート不在・レンダリングエラー・mPDF内部エラーのいずれもここで一元的に捕捉する
            // 【テスト要件対応】: 運用調査に必要な情報（テンプレート名・例外内容・スタックトレース）をログへ記録する（EDGE-003）
            // 【個人情報配慮】: $data（顧客情報を含みうる）はログへ出力しない
            Log::error('PDF生成に失敗しました。', [
                'view' => $view,
                'exception' => $e,
            ]);

            // 【テスト要件対応】: ユーザー向けに再試行を促すメッセージを持つ独自例外へラップしてスローする（EDGE-003）
            throw new PdfGenerationException($view, $e);
        }
    }

    /**
     * 【機能概要】: 帳票種別・識別子・年度から、命名規則に従った保存先パス（Storageディスク基準の相対パス）を生成する
     * 【実装方針】: `pdf/{帳票種別}/{年度}/{帳票種別}_{識別子}.pdf` の形式で組み立てる。
     *               年度は明示的に指定された場合はその値を採用し、未指定の場合は呼び出し時点（生成時点）の年を採用する。
     *               この方針により、同一引数に対して常に同一のパスが生成される（冪等性、TC10対応）。
     * 【テスト対応】: TC3, TC4（命名規則どおりのパス生成）, TC10（年度境界での冪等性）
     * 🟡 信頼性レベル: タスクファイル実装詳細4「ファイル名の命名規則（quotation_{採番番号}.pdf 等）」「storage/app/pdf/{帳票種別}/{年度}/」の指示に基づく
     *               （年度の決定方法は明記がないため、「明示指定がなければ生成時点の年を採用する」という方針を実装時に定めた＝🔴推測部分）
     *
     * @param string $type 帳票種別（'quotation' | 'shipment' | 'invoice' 等）
     * @param string $identifier 識別子（採番番号・出荷ID・請求書番号等）
     * @param int|null $year 年度（省略時は生成時点の年を採用）
     * @return string Storageディスク基準の相対パス（例: 'pdf/quotation/2026/quotation_Q-2026-0001.pdf'）
     */
    public function buildStoragePath(string $type, string $identifier, ?int $year = null): string
    {
        // 【年度決定ロジック】: 明示指定があればそれを優先し、なければ生成時点（now()）の年を採用する
        // 【冪等性の確保】: 同一引数（type, identifier, year）であれば常に同じパスを返すことを保証する
        $resolvedYear = $year ?? (int) now()->year;

        // 【パス組み立て】: ディレクトリ階層は帳票種別→年度の順、ファイル名は「{帳票種別}_{識別子}.pdf」とする
        // 【命名規則】: quotation_{採番番号}.pdf / shipment_{出荷ID}.pdf / invoice_{請求書番号}.pdf のいずれも
        //               「{帳票種別}_{識別子}.pdf」という共通ルールに集約できるため、帳票種別をそのままプレフィックスに用いる
        return sprintf('pdf/%s/%d/%s_%s.pdf', $type, $resolvedYear, $type, $identifier);
    }

    /**
     * 【機能概要】: PDFを生成し、storage/app/pdf/配下の規定パスへ保存して、保存先パスを返す
     * 【実装方針】: generateFromView()でPDFバイナリを生成し、buildStoragePath()で決定した規定パスへ
     *               Storageファサード（localディスク）経由で保存する。
     *               ダウンロード時は都度生成を基本とし、本メソッドによる保存は履歴・再ダウンロード用途とする。
     * 【テスト対応】: TC5（生成→保存→再取得の統合フロー）
     * 🟡 信頼性レベル: タスクファイル実装詳細4「Storageファサードを用いて保存する」「ダウンロード時は都度生成を基本とし、保存は履歴・再ダウンロード用途とする」に基づく
     *
     * @param string $view Bladeビュー名
     * @param array<string, mixed> $data ビューに渡すデータ配列
     * @param string $type 帳票種別（'quotation' | 'shipment' | 'invoice' 等）
     * @param string $identifier 識別子（採番番号・出荷ID・請求書番号等）
     * @return string 保存先パス（Storageディスク基準の相対パス）
     *
     * @throws PdfGenerationException PDF生成またはファイル保存に失敗した場合
     */
    public function generateAndStore(string $view, array $data, string $type, string $identifier): string
    {
        // 【処理内容】: まずPDFバイナリを生成する（失敗時はgenerateFromView内でPdfGenerationExceptionへラップ・ログ記録される）
        $binary = $this->generateFromView($view, $data);

        // 【処理内容】: 命名規則に従った保存先パスを決定する
        $path = $this->buildStoragePath($type, $identifier);

        try {
            // 【ファイル保存】: Storageファサード（localディスク）経由でstorage/app/pdf/配下へ保存する
            Storage::disk('local')->put($path, $binary);
        } catch (Throwable $e) {
            // 【エラー捕捉】: ファイル書き込み失敗（ディスク容量不足・権限不足等）もEDGE-003の対象として統一的に扱う
            Log::error('PDFファイルの保存に失敗しました。', [
                'view' => $view,
                'path' => $path,
                'exception' => $e,
            ]);

            throw new PdfGenerationException($view, $e);
        }

        // 【結果返却】: 後続タスク（見積/納品/請求のPDF出力機能）が一貫した方法で再取得できるよう、保存先パスを返す
        return $path;
    }
}
