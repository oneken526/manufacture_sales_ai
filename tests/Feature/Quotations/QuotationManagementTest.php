<?php

namespace Tests\Feature\Quotations;

use App\Enums\OrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0008 統合テスト1・2、単体テスト要件テストケース4、境界値TC13に対応するテスト（Redフェーズ）。
 *
 * 現時点では Quotation/QuotationItem モデル・QuotationController・関連ビュー・ルーティングが
 * 未実装のため、本テストはルート未定義（RouteNotFoundException）またはクラス未検出によりすべて失敗する。
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0008/quotation-testcases.md TC3, TC4, TC5, TC7, TC8, TC9, TC13
 */
class QuotationManagementTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => true]);
    }

    /**
     * 【テスト目的】: salesロールユーザーが見積を作成すると、見積番号が採番され詳細画面に保存内容が表示されることを確認する
     * 【テスト内容】: 顧客・複数明細・有効期限・備考を入力して見積を保存し、詳細画面で表示内容を確認する
     * 【期待される動作】: 見積番号がQUO-{年度}-{連番}形式で採番され、詳細画面に顧客名・見積番号・明細・合計金額が表示される
     * 🔵 信頼性レベル: TASK-0008.md完了条件「QuotationControllerにより一覧・作成フォーム・保存・詳細...が動作すること」・REQ-030より直接抽出
     */
    public function test_sales_user_can_create_quotation_and_view_its_detail(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create(['company_name' => '株式会社見積テスト商事']);
        $productA = Product::factory()->create(['product_name' => 'テスト製品A', 'unit_price' => 1000]);
        $productB = Product::factory()->create(['product_name' => 'テスト製品B', 'unit_price' => 2000]);
        $year = (int) now()->year;

        // 【実際の処理実行】: sales権限のユーザーで見積作成フォームから顧客・明細2行・有効期限・備考を入力して保存する
        // 【処理内容】: REQ-030の構成要素（顧客選択・製品行追加・数量・単価・備考・有効期限）を含む典型的な入力を送信する
        $storeResponse = $this->actingAs($sales)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $productA->id, 'quantity' => 2, 'unit_price' => 1000],
                ['product_id' => $productB->id, 'quantity' => 3, 'unit_price' => 2000],
            ],
            'expires_at' => now()->addDays(30)->toDateString(),
            'remarks' => '統合テスト用の見積です',
        ]);

        $quotation = Quotation::where('customer_id', $customer->id)->firstOrFail();

        // 【結果検証】: 見積番号がQUO-{年度}-{連番4桁}形式で採番され、詳細画面へリダイレクトされることを確認する
        $storeResponse->assertRedirect(route('quotations.show', $quotation)); // 【確認内容】: 保存後に詳細画面へリダイレクトされることを確認 🔵
        $this->assertMatchesRegularExpression('/^QUO-'.$year.'-\d{4}$/', $quotation->quotation_number); // 【確認内容】: 見積番号がQUO-{年度}-{連番4桁}形式で採番されることを確認 🔵
        $this->assertDatabaseHas('quotation_items', [
            'quotation_id' => $quotation->id,
            'product_id' => $productA->id,
            'quantity' => 2,
            'unit_price' => 1000,
        ]); // 【確認内容】: 明細が正しく保存されることを確認 🔵

        // 【実際の処理実行】: 見積詳細画面を表示する
        $showResponse = $this->actingAs($sales)->get(route('quotations.show', $quotation));

        // 【結果検証】: 詳細画面に顧客名・見積番号・明細内容・合計金額が表示されることを確認する
        $showResponse->assertOk();
        $showResponse->assertSee('株式会社見積テスト商事'); // 【確認内容】: 顧客名が表示されることを確認 🔵
        $showResponse->assertSee($quotation->quotation_number); // 【確認内容】: 採番された見積番号が表示されることを確認 🔵
        $showResponse->assertSee('テスト製品A'); // 【確認内容】: 明細の製品名が表示されることを確認 🔵
        $showResponse->assertSee('8000'); // 【確認内容】: 合計金額(2*1000+3*2000=8000)が表示されることを確認 🔵
    }

    /**
     * 【テスト目的】: 見積詳細画面からPDFプレビュー・ダウンロードが正常に行われることを確認する
     * 【テスト内容】: 明細・顧客情報・有効期限が設定された見積に対しGET /quotations/{quotation}/pdfを実行する
     * 【期待される動作】: レスポンスのContent-Typeがapplication/pdfであり、PDFバイナリが返却される
     * 🔵 信頼性レベル: TASK-0008.md実装詳細4・完了条件「見積PDF（QuotationPdfテンプレート）が...生成・ダウンロードできること（REQ-032）」より直接抽出
     */
    public function test_quotation_pdf_can_be_previewed_and_downloaded(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create();
        $quotation = Quotation::factory()->create(['customer_id' => $customer->id]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'unit_price' => 1500,
        ]);

        // 【実際の処理実行】: 見積詳細画面からPDFプレビュー・ダウンロードのエンドポイントを呼び出す
        // 【処理内容】: QuotationController::pdf()がPdfServiceを介してQuotationPdfテンプレートをレンダリングしPDFバイナリを返す想定
        $pdfResponse = $this->actingAs($sales)->get(route('quotations.pdf', $quotation));

        // 【結果検証】: レスポンスが正常(200)であり、Content-Typeがapplication/pdfであることを確認する
        // 【期待値確認】: REQ-032「見積のPDFプレビュー・ダウンロードができなければならない」を満たすことを確認するため
        $pdfResponse->assertOk(); // 【確認内容】: PDFレスポンスが正常に返却されることを確認 🔵
        $pdfResponse->assertHeader('Content-Type', 'application/pdf'); // 【確認内容】: レスポンスヘッダーがPDF形式であることを確認 🔵
    }

    /**
     * 【テスト目的】: 見積作成→PDF出力→受注確定→在庫引当の一連フローがエラーなく完了することを確認する
     * 【テスト内容】: salesユーザーが見積を保存し、PDFを表示し、受注確定操作を行い、関連テーブルが整合して更新されることを確認する
     * 【期待される動作】: 受注確定後にreserved_quantity加算・stock_movements記録・sales_orders作成・quotations.status更新が反映され、成功メッセージが表示される
     * 🔵 信頼性レベル: TASK-0008.md統合テスト1のシナリオ・期待結果より直接抽出
     */
    public function test_full_flow_from_quotation_creation_to_order_confirmation_reserves_stock(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 100, 'reserved_quantity' => 0]);

        // 【実際の処理実行】: 見積を作成し保存する
        $this->actingAs($sales)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 15, 'unit_price' => 1000],
            ],
            'expires_at' => now()->addDays(30)->toDateString(),
        ]);
        $quotation = Quotation::where('customer_id', $customer->id)->firstOrFail();

        // 【実際の処理実行】: PDFプレビューを開く
        $this->actingAs($sales)->get(route('quotations.pdf', $quotation))->assertOk();

        // 【実際の処理実行】: 見積詳細画面から「受注確定」を実行する
        // 【処理内容】: QuotationController::confirm() → QuotationService::confirmToOrder()が呼び出される想定
        $confirmResponse = $this->actingAs($sales)->post(route('quotations.confirm', $quotation));

        // 【結果検証】: 受注確定後、reserved_quantity加算・stock_movements記録・sales_orders作成・quotations.status更新がDBに反映されることを確認する
        $confirmResponse->assertRedirect(route('quotations.show', $quotation));
        $confirmResponse->assertSessionHas('success', '受注を確定しました'); // 【確認内容】: 成功メッセージがフラッシュされることを確認 🔵
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 15,
        ]); // 【確認内容】: 在庫引当によりreserved_quantityが加算されることを確認 🔵
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'reason' => StockMovementReason::RESERVATION->value,
            'quantity_change' => 15,
        ]); // 【確認内容】: 在庫変動履歴(reason=1 reservation)が記録されることを確認 🔵
        $this->assertDatabaseHas('sales_orders', [
            'quotation_id' => $quotation->id,
            'status' => OrderStatus::CONFIRMED->value,
        ]); // 【確認内容】: 受注が確定済みステータスで作成されることを確認 🔵
        $this->assertSame(QuotationStatus::CONVERTED, $quotation->fresh()->status); // 【確認内容】: 元の見積ステータスがconvertedへ更新されることを確認 🔵

        // 【結果検証】: 詳細画面に成功メッセージ「受注を確定しました」が表示されることを確認する
        $afterResponse = $this->actingAs($sales)->get(route('quotations.show', $quotation));
        $afterResponse->assertSee('受注を確定しました'); // 【確認内容】: 成功メッセージが画面に表示されることを確認 🔵
    }

    /**
     * 【テスト目的】: 在庫不足により受注確定が中止され、警告メッセージが表示されることを確認する（EDGE-001）
     * 【テスト内容】: 利用可能在庫が明細数量を下回るよう調整した見積に対し受注確定操作を行う
     * 【期待される動作】: 「在庫が不足しています」という警告メッセージが表示され、DBにいかなる変更も反映されない
     * 🔵 信頼性レベル: TASK-0008.md統合テスト2のシナリオ・実装詳細6より直接抽出
     */
    public function test_order_confirmation_is_aborted_with_warning_when_stock_is_insufficient(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        // 【テストデータ準備】: 利用可能在庫(10-5=5)が明細数量(8)を下回る不足状態を事前に作る
        // 【実際の発生シナリオ】: 他の受注で引当済みになり利用可能在庫が不足しているケースを再現する
        $product = Product::factory()->create(['stock_quantity' => 10, 'reserved_quantity' => 5]);
        $quotation = Quotation::factory()->create(['customer_id' => $customer->id, 'status' => QuotationStatus::DRAFT]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 8,
            'unit_price' => 1000,
        ]);

        // 【実際の処理実行】: 在庫不足状態で受注確定操作を実行する
        // 【処理内容】: QuotationController::confirm()がInsufficientStockExceptionを捕捉し、警告メッセージをフラッシュする想定
        $confirmResponse = $this->actingAs($sales)->post(route('quotations.confirm', $quotation));

        // 【結果検証】: 詳細画面へリダイレクトされ、「在庫が不足しています」という警告メッセージが表示されることを確認する
        $confirmResponse->assertRedirect(route('quotations.show', $quotation));
        $confirmResponse->assertSessionHas('warning'); // 【確認内容】: 警告メッセージがセッションにフラッシュされることを確認 🔵

        $afterResponse = $this->actingAs($sales)->get(route('quotations.show', $quotation));
        $afterResponse->assertSee('在庫が不足しています'); // 【確認内容】: 画面上に在庫不足の警告メッセージが表示されることを確認 🔵

        // 【結果検証】: reserved_quantity・stock_movements・sales_orders・quotations.statusのいずれにも変更が反映されていないことを確認する
        // 【システムの安全性】: トランザクションのロールバックによりデータ不整合が発生しないことを保証する
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'reserved_quantity' => 5,
        ]); // 【確認内容】: 在庫不足時にreserved_quantityが変化しないことを確認 🔵
        $this->assertDatabaseMissing('sales_orders', ['quotation_id' => $quotation->id]); // 【確認内容】: 受注レコードが作成されないことを確認 🔵
        $this->assertSame(QuotationStatus::DRAFT, $quotation->fresh()->status); // 【確認内容】: 見積ステータスがdraftのまま変化しないことを確認 🔵
    }

    /**
     * 【テスト目的】: 明細行が1件もない状態で見積を保存しようとするとバリデーションエラーになることを確認する
     * 【テスト内容】: itemsを空配列にした状態でPOST /quotationsを実行する
     * 【期待される動作】: バリデーションエラーが返却され、「明細を1件以上追加してください」等のメッセージが表示され、見積が保存されない
     * 🟡 信頼性レベル: TASK-0008.md単体テスト要件テストケース4（EDGE-011から妥当に推測と明記）より
     */
    public function test_quotation_with_no_items_fails_validation_on_store(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();

        // 【実際の処理実行】: 明細行を1件も追加せず見積保存処理を実行する
        // 【不正な理由】: StoreQuotationRequestのバリデーションルールで明細が1件以上必須と定義される想定のため
        $storeResponse = $this->actingAs($sales)->post(route('quotations.store'), [
            'customer_id' => $customer->id,
            'items' => [],
            'expires_at' => now()->addDays(30)->toDateString(),
        ]);

        // 【結果検証】: バリデーションエラーが返却され、見積が保存されないことを確認する
        // 【システムの安全性】: 不正な状態のレコードがDBに保存されることを未然に防ぐ
        $storeResponse->assertSessionHasErrors('items'); // 【確認内容】: itemsフィールドにバリデーションエラーが発生することを確認 🟡
        $this->assertDatabaseCount('quotations', 0); // 【確認内容】: 見積が1件も保存されないことを確認 🟡
        $this->assertDatabaseCount('quotation_items', 0); // 【確認内容】: 見積明細も保存されないことを確認 🟡
    }

    /**
     * 【テスト目的】: 有効期限が過ぎた見積を受注確定しようとすると処理が拒否されることを確認する（REQ-033）
     * 【テスト内容】: expires_atに過去日が設定された見積に対しPOST /quotations/{quotation}/confirmを実行する
     * 【期待される動作】: 受注確定処理が中止され、有効期限切れである旨のメッセージが表示され、DBの状態が変化しない
     * 🟡 信頼性レベル: TASK-0008.md実装詳細7「期限切れの見積に対しては受注確定操作を不可とし、再見積の作成を促すメッセージを表示する」より
     */
    public function test_expired_quotation_cannot_be_confirmed_to_order(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 100, 'reserved_quantity' => 0]);

        // 【テストデータ準備】: 有効期限(expires_at)が昨日の日付である見積を準備する
        // 【実際の発生シナリオ】: 顧客からの返答が遅れ、見積の有効期限を過ぎてから受注確定操作が行われるケースを再現する
        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'expires_at' => now()->subDay()->toDateString(),
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 1000,
        ]);

        // 【実際の処理実行】: 期限切れの見積に対して受注確定操作を実行する
        $confirmResponse = $this->actingAs($sales)->post(route('quotations.confirm', $quotation));

        // 【結果検証】: 受注確定処理が中止され、期限切れである旨のメッセージが表示されることを確認する
        $confirmResponse->assertSessionHas('warning'); // 【確認内容】: 警告メッセージがセッションにフラッシュされることを確認 🟡
        $afterResponse = $this->actingAs($sales)->get(route('quotations.show', $quotation));
        $afterResponse->assertSee('有効期限'); // 【確認内容】: 有効期限切れに関するメッセージが画面に表示されることを確認 🟡

        // 【結果検証】: DBの状態（reserved_quantity・sales_orders・quotations.status）が変更されないことを確認する
        $this->assertDatabaseHas('products', ['id' => $product->id, 'reserved_quantity' => 0]); // 【確認内容】: 在庫が変更されないことを確認 🟡
        $this->assertDatabaseMissing('sales_orders', ['quotation_id' => $quotation->id]); // 【確認内容】: 受注が作成されないことを確認 🟡
    }

    /**
     * 【テスト目的】: 明細が0件の見積の詳細画面で、受注確定ボタンが非活性化されて表示されることを確認する（EDGE-011）
     * 【テスト内容】: quotation_itemsが0件の見積の詳細画面を表示し、確定ボタンのdisabled状態と理由表示を確認する
     * 【期待される動作】: 「受注確定」ボタンにdisabled属性が付与され、非活性の理由が補足テキストとして表示される
     * 🟡 信頼性レベル: TASK-0008.md完了条件「明細が0件の場合に受注確定ボタンが非活性化されること（EDGE-011）」・要件定義書でも🟡（UI/UXから推測）と明記
     */
    public function test_confirm_button_is_disabled_when_quotation_has_no_items(): void
    {
        $sales = $this->user(UserRole::SALES);
        $customer = Customer::factory()->create();

        // 【テストデータ準備】: 明細が1件も登録されていない見積を準備する
        // 【境界値選択の根拠】: 「ボタンが活性化されるか否か」を分岐させる最小の境界（0件）を選定する
        $quotation = Quotation::factory()->create(['customer_id' => $customer->id, 'status' => QuotationStatus::DRAFT]);

        // 【実際の処理実行】: 明細0件の見積の詳細画面を表示する
        $showResponse = $this->actingAs($sales)->get(route('quotations.show', $quotation));

        // 【結果検証】: 「受注確定」ボタンがdisabled属性付きで表示され、非活性の理由が表示されることを確認する
        // 【堅牢性の確認】: 境界値（0件）でのUI制御が確実に機能し、不正な受注確定操作を未然に防ぐ
        $showResponse->assertOk();
        $showResponse->assertSee('disabled'); // 【確認内容】: 受注確定ボタンにdisabled属性が含まれることを確認 🟡
        $showResponse->assertSee('明細が登録されていないため受注確定できません'); // 【確認内容】: 非活性の理由が補足テキストとして表示されることを確認 🟡
    }
}
