<?php

namespace Tests\Feature;

use App\Exceptions\PdfGenerationException;
use App\Services\PdfService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\MpdfException;
use Smalot\PdfParser\Parser as PdfParser;
use Tests\TestCase;

/**
 * TASK-0004 統合テスト要件1・2（生成→保存→再取得フロー、エラーハンドリングフロー）に対応するテスト。
 * 対象テストケース: TC5, TC8（pdf-service-testcases.md）
 *
 * generateAndStore() / 共通レイアウト(pdf.layouts.base) / config/company.php / PdfGenerationException は
 * 未実装・未作成のため、本テストは現時点で失敗する想定。
 *
 * @see docs/implements/manufacture-sales-system/TASK-0004/pdf-service-testcases.md
 */
class PdfServiceIntegrationTest extends TestCase
{
    private PdfService $pdfService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdfService = app(PdfService::class);

        // 【テスト前準備】: 保存先ディスク（local）をフェイクに差し替え、実ファイルシステムを汚さないようにする
        // 【環境初期化】: 各テスト独立性を保つため、Storageをフェイク化してテストごとにクリーンな状態にする
        Storage::fake('local');
    }

    public function test_generate_store_and_retrieve_pdf_with_common_layout(): void
    {
        // 【テスト目的】: PdfServiceを介してPDF生成→ファイル保存→再取得までの一連の流れがエラーなく完了することを確認する
        // 【テスト内容】: 共通レイアウト（ヘッダー・フッター・会社情報）を継承したテンプレートからPDFを生成し、
        //                 storage/app/pdf/配下の規定パスへ保存、保存ファイルを再取得して内容を検証する
        // 【期待される動作】: 保存ファイルがPDFとして開けること（破損していないこと）、
        //                     ヘッダー・フッターに会社情報（config/company.phpの会社名）が含まれること
        // 🟡 信頼性レベル: タスクファイル統合テスト1（共通レイアウトの内容は推測を含む）に基づく

        // 【テストデータ準備】: 共通レイアウトを@extendsする簡易帳票テンプレートと、帳票データを用意する
        // 【初期条件設定】: 帳票種別=quotation、識別子=Q-2026-0001として保存パスが命名規則に従うことも併せて検証する
        $data = [
            'title' => '統合テスト用簡易帳票',
            'issuedAt' => '2026-06-07',
            'subject' => 'PdfService統合動作確認',
        ];

        // 【実際の処理実行】: generateAndStore()でPDF生成から保存までを一括実行する
        // 【処理内容】: 内部でgenerateFromView()→buildStoragePath()→Storage::put()の順に処理される想定
        $path = $this->pdfService->generateAndStore('pdf.fixtures.sample-report', $data, 'quotation', 'Q-2026-0001');

        // 【結果検証】: 返却されたパスが命名規則どおりであり、Storage上にファイルが実在することを確認する
        // 【期待値確認】: 統合テスト要件1の「規定パスに保存」「ファイルとして開けること」に対応する
        $this->assertStringContainsString('pdf/quotation/', $path); // 【検証項目】: 帳票種別ディレクトリを含む規定パスであること 🟡
        $this->assertStringEndsWith('quotation_Q-2026-0001.pdf', $path); // 【検証項目】: 命名規則どおりのファイル名であること 🟡
        Storage::disk('local')->assertExists($path); // 【検証項目】: ファイルがStorage上に実在すること（保存が完了していること） 🟡

        // 【結果検証】: 保存されたファイルを再取得し、PDFとして破損なく開けること、共通レイアウトの会社情報が含まれることを確認する
        $savedBinary = Storage::disk('local')->get($path);
        $this->assertStringStartsWith('%PDF-', $savedBinary); // 【検証項目】: 保存ファイルがPDFとして有効であり破損していないこと 🟡

        $parser = new PdfParser();
        $document = $parser->parseContent($savedBinary);
        $extractedText = $document->getText();

        $this->assertStringContainsString('統合テスト用簡易帳票', $extractedText); // 【検証項目】: 帳票テンプレート側のコンテンツが正しく描画されていること 🟡
        $this->assertStringContainsString(config('company.name'), $extractedText); // 【検証項目】: 共通レイアウトのヘッダー・フッターに会社情報（config/company.php）が含まれること 🟡
    }

    public function test_pdf_generation_failure_propagates_and_is_logged(): void
    {
        // 【テスト目的】: mPDF内部で予期しない例外が発生した場合に、PdfGenerationExceptionが呼び出し元まで伝播し、
        //               ログに詳細なエラー内容（テンプレート名・例外メッセージ・スタックトレース）が記録されることを確認する
        // 【エラーケースの概要】: フォント読み込み失敗・一時ディレクトリ書込み権限不足等、mPDF内部処理で発生しうる例外をモックで再現する
        // 【エラー処理の重要性】: ライブラリ内部の例外も統一的に独自例外へ変換し、画面表示・ログ記録の一連の流れが機能する必要がある
        // 🟡 信頼性レベル: タスクファイル統合テスト2（モックによる擬似的エラー）に基づく

        // 【テストデータ準備】: mPDFのOutput()呼び出し時に例外をスローするモックを用意する
        // 【不正な理由】: 本番環境でのフォント未配置・ディスク容量不足により発生しうる状況を模擬する
        $mpdfMock = \Mockery::mock(Mpdf::class);
        $mpdfMock->shouldReceive('WriteHTML')->once();
        $mpdfMock->shouldReceive('Output')->once()->andThrow(new MpdfException('mock: フォント読み込みに失敗しました'));
        $this->app->bind(Mpdf::class, fn () => $mpdfMock);

        Log::shouldReceive('error')->once()->withArgs(function (string $message, array $context = []): bool {
            // 【期待値確認】: ログにテンプレート名・例外メッセージ等の運用調査に必要な情報が含まれることを確認する
            return isset($context['view']) && isset($context['exception']); // 【検証項目】: ログのコンテキストにテンプレート名と例外情報が含まれること 🟡
        });

        // 【実際の処理実行】: モック化したmPDFを用いてgenerateFromView()を呼び出す
        $this->expectException(PdfGenerationException::class);
        $this->expectExceptionMessage('再度お試しください'); // 【検証項目】: ユーザーへ分かりやすい再試行メッセージが含まれること（統合テスト要件2） 🟡

        $this->pdfService->generateFromView('pdf.test-template', ['title' => 'エラー確認', 'issuedAt' => '2026-06-07']);
    }
}
