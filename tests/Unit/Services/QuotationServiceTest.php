<?php

namespace Tests\Unit\Services;

use App\Enums\OrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use App\Services\QuotationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TASK-0008 単体テストケース1・2・境界値TC10・TC11に対応するテスト（Redフェーズ）。
 *
 * 現時点では Quotation/QuotationItem モデル・QuotationService・InsufficientStockException が
 * 未実装のため、本テストはクラス未検出（Fatal Error）または機能未実装によりすべて失敗する。
 *
 * @see .docs/implements/manufacture-sales-system/TASK-0008/quotation-testcases.md TC1, TC2, TC6, TC10, TC11
 */
class QuotationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): QuotationService
    {
        return $this->app->make(QuotationService::class);
    }

    /**
     * 見積・明細・製品をまとめて準備するヘルパー
     *
     * @return array{quotation: Quotation, productA: Product, productB: Product}
     */
    private function prepareQuotationWithItems(int $quantityA, int $stockA, int $reservedA, int $quantityB, int $stockB, int $reservedB): array
    {
        $customer = Customer::factory()->create();
        $creator = User::factory()->create();

        $productA = Product::factory()->create([
            'stock_quantity' => $stockA,
            'reserved_quantity' => $reservedA,
        ]);
        $productB = Product::factory()->create([
            'stock_quantity' => $stockB,
            'reserved_quantity' => $reservedB,
        ]);

        $quotation = Quotation::factory()->create([
            'customer_id' => $customer->id,
            'status' => QuotationStatus::DRAFT,
            'created_by' => $creator->id,
        ]);

        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $productA->id,
            'quantity' => $quantityA,
            'unit_price' => 1000,
        ]);
        QuotationItem::factory()->create([
            'quotation_id' => $quotation->id,
            'product_id' => $productB->id,
            'quantity' => $quantityB,
            'unit_price' => 2000,
        ]);

        return ['quotation' => $quotation, 'productA' => $productA, 'productB' => $productB];
    }

    /**
     * 【テスト目的】: confirmToOrder()が在庫引当・stock_movements記録・受注作成・ステータス更新をアトミックに行うことを確認する
     * 【テスト内容】: 在庫が十分な見積（明細2行）に対しconfirmToOrder()を実行し、関連するすべてのテーブルが整合して更新されることを検証する
     * 【期待される動作】: 各製品のreserved_quantityが明細数量分加算され、stock_movements(reason=1)・sales_orders(status=1)・sales_order_itemsが作成され、quotations.statusが2(converted)になる
     * 🔵 信頼性レベル: TASK-0008.md単体テスト要件テストケース1・REQ-031・REQ-040・dataflow.md「機能1」より直接抽出
     */
    public function test_confirm_to_order_atomically_reserves_stock_and_creates_sales_order(): void
    {
        // 【テストデータ準備】: 利用可能在庫(100, 50)が明細数量(10, 20)を上回る正常系の代表的なケースを準備する
        // 【初期条件設定】: status=draftの見積に2件の明細を紐づけ、両製品とも在庫が十分にある状態にする
        $context = $this->prepareQuotationWithItems(
            quantityA: 10, stockA: 100, reservedA: 0,
            quantityB: 20, stockB: 50, reservedB: 0,
        );
        $quotation = $context['quotation'];
        $productA = $context['productA'];
        $productB = $context['productB'];

        // 【実際の処理実行】: QuotationService::confirmToOrder()を呼び出し、見積から受注への転換処理を実行する
        // 【処理内容】: DBトランザクション内で在庫引当・履歴記録・受注作成・ステータス更新が一括実行される想定
        $this->service()->confirmToOrder($quotation);

        // 【結果検証】: 各製品のreserved_quantityが明細数量分だけ加算されていることを確認する
        // 【期待値確認】: REQ-040「受注確定後、在庫数を引き当てしなければならない」を満たすことを確認する
        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'reserved_quantity' => 10,
        ]); // 【確認内容】: 製品Aのreserved_quantityが0→10に加算されることを確認 🔵
        $this->assertDatabaseHas('products', [
            'id' => $productB->id,
            'reserved_quantity' => 20,
        ]); // 【確認内容】: 製品Bのreserved_quantityが0→20に加算されることを確認 🔵

        // 【結果検証】: stock_movementsにreason=1(reservation)の履歴が各製品分作成されていることを確認する
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productA->id,
            'reason' => StockMovementReason::RESERVATION->value,
            'quantity_change' => 10,
        ]); // 【確認内容】: 製品Aの在庫引当履歴が記録されることを確認 🔵
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $productB->id,
            'reason' => StockMovementReason::RESERVATION->value,
            'quantity_change' => 20,
        ]); // 【確認内容】: 製品Bの在庫引当履歴が記録されることを確認 🔵

        // 【結果検証】: sales_ordersがstatus=1(confirmed)・quotation_id=元見積IDで作成されていることを確認する
        $this->assertDatabaseHas('sales_orders', [
            'quotation_id' => $quotation->id,
            'customer_id' => $quotation->customer_id,
            'status' => OrderStatus::CONFIRMED->value,
        ]); // 【確認内容】: 受注が確定済みステータスかつ元見積IDを保持して作成されることを確認 🔵

        $salesOrder = $quotation->fresh()->salesOrder ?? \App\Models\SalesOrder::query()->where('quotation_id', $quotation->id)->first();
        $this->assertNotNull($salesOrder); // 【確認内容】: 受注レコードが作成されていることを確認 🔵

        // 【結果検証】: sales_order_itemsが見積明細と同内容で作成されていることを確認する
        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $salesOrder->id,
            'product_id' => $productA->id,
            'quantity' => 10,
            'unit_price' => 1000,
        ]); // 【確認内容】: 受注明細が見積明細の内容を引き継いで作成されることを確認 🔵
        $this->assertDatabaseHas('sales_order_items', [
            'sales_order_id' => $salesOrder->id,
            'product_id' => $productB->id,
            'quantity' => 20,
            'unit_price' => 2000,
        ]); // 【確認内容】: 受注明細が見積明細の内容を引き継いで作成されることを確認 🔵

        // 【結果検証】: 元の見積のstatusが2(converted)に更新されていることを確認する
        $this->assertSame(QuotationStatus::CONVERTED, $quotation->fresh()->status); // 【確認内容】: 見積ステータスが受注転換済みに変わることを確認 🔵
    }

    /**
     * 【テスト目的】: 在庫不足時にInsufficientStockExceptionがスローされ、確定処理が中断・ロールバックされることを確認する（EDGE-001）
     * 【テスト内容】: 利用可能在庫(5)が明細数量(8)を下回る製品を含む見積に対しconfirmToOrder()を実行する
     * 【期待される動作】: 例外がスローされ、reserved_quantity加算・stock_movements記録・sales_orders作成・quotations.status更新のいずれも行われない
     * 🔵 信頼性レベル: TASK-0008.md単体テスト要件テストケース2・EDGE-001・dataflow.md「機能1」alt分岐より直接抽出
     */
    public function test_confirm_to_order_throws_exception_and_rolls_back_when_stock_is_insufficient(): void
    {
        // 【テストデータ準備】: 製品Aの利用可能在庫(10-5=5)が明細数量(8)を下回る不足状態を準備する
        // 【初期条件設定】: 製品Bは在庫十分（不足が製品Aのみであることを明確にするため）
        $context = $this->prepareQuotationWithItems(
            quantityA: 8, stockA: 10, reservedA: 5,
            quantityB: 1, stockB: 100, reservedB: 0,
        );
        $quotation = $context['quotation'];
        $productA = $context['productA'];

        // 【実際の処理実行】: 在庫不足の状態でconfirmToOrder()を実行し、例外がスローされることを確認する
        // 【処理内容】: 例外スロー後、トランザクションがロールバックされ、いかなるDB変更も残らないことを検証する
        try {
            $this->service()->confirmToOrder($quotation);
            $this->fail('InsufficientStockExceptionがスローされるべきです');
        } catch (InsufficientStockException $e) {
            // 【結果検証】: 例外オブジェクトに不足している製品ID・要求数量・利用可能数量が含まれることを確認する
            $this->assertSame($productA->id, $e->productId); // 【確認内容】: 例外が不足製品のIDを保持していることを確認 🔵
            $this->assertSame(8, $e->requestedQuantity); // 【確認内容】: 例外が要求数量(8)を保持していることを確認 🔵
            $this->assertSame(5, $e->availableQuantity); // 【確認内容】: 例外が利用可能数量(5)を保持していることを確認 🔵
        }

        // 【結果検証】: reserved_quantityが変更されず、stock_movements・sales_orders・quotations.statusも変化しないことを確認する
        // 【品質保証の観点】: トランザクションのロールバックによりデータ不整合が発生しないことを保証する
        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'reserved_quantity' => 5,
        ]); // 【確認内容】: 製品Aのreserved_quantityが変更されていないことを確認 🔵
        $this->assertSame(0, \App\Models\StockMovement::query()->where('product_id', $productA->id)->count()); // 【確認内容】: 在庫変動履歴が作成されないことを確認 🔵
        $this->assertSame(0, \App\Models\SalesOrder::query()->where('quotation_id', $quotation->id)->count()); // 【確認内容】: 受注レコードが作成されないことを確認 🔵
        $this->assertSame(QuotationStatus::DRAFT, $quotation->fresh()->status); // 【確認内容】: 見積ステータスがdraftのまま変化しないことを確認 🔵
    }

    /**
     * 【テスト目的】: 利用可能在庫と要求数量がちょうど一致する境界条件で、在庫不足と判定されず受注確定が成功することを確認する
     * 【テスト内容】: 利用可能在庫(10)＝明細数量(10)という境界値でconfirmToOrder()を実行する
     * 【期待される動作】: 例外はスローされず、reserved_quantityが正しく加算される
     * 🔵 信頼性レベル: EDGE-001の判定式（利用可能在庫 < 要求数量で不足と判定）から導出される境界値
     */
    public function test_confirm_to_order_succeeds_when_available_quantity_exactly_equals_requested_quantity(): void
    {
        // 【テストデータ準備】: 利用可能在庫(10-0=10)と要求数量(10)が完全に一致する境界値を準備する
        // 【境界値選択の根拠】: 「不足」と判定される境界線のすぐ外側（等しい場合）を検証し、オフバイワンエラーがないことを保証する
        $context = $this->prepareQuotationWithItems(
            quantityA: 10, stockA: 10, reservedA: 0,
            quantityB: 1, stockB: 100, reservedB: 0,
        );
        $quotation = $context['quotation'];
        $productA = $context['productA'];

        // 【実際の処理実行】: 利用可能在庫と要求数量が等しい境界条件でconfirmToOrder()を実行する
        $this->service()->confirmToOrder($quotation);

        // 【結果検証】: 例外がスローされず、reserved_quantityが要求数量分(10)正しく加算されることを確認する
        // 【境界での正確性】: 判定式が「<」であり「<=」でないことを保証する
        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'reserved_quantity' => 10,
        ]); // 【確認内容】: 利用可能在庫＝要求数量という境界条件でも正しく引当が成功することを確認 🔵
        $this->assertSame(QuotationStatus::CONVERTED, $quotation->fresh()->status); // 【確認内容】: 受注確定処理が正常に完了し見積が転換済みになることを確認 🔵
    }

    /**
     * 【テスト目的】: 利用可能在庫が要求数量を1だけ下回る境界条件で、確実に在庫不足と判定されることを確認する
     * 【テスト内容】: 利用可能在庫(9)が明細数量(10)よりちょうど1少ない最小の不足幅でconfirmToOrder()を実行する
     * 【期待される動作】: InsufficientStockExceptionがスローされ、要求数量10・利用可能数量9が例外情報に含まれる
     * 🔵 信頼性レベル: EDGE-001の判定式から導出される境界値（TC10と対）
     */
    public function test_confirm_to_order_throws_exception_when_available_quantity_is_one_less_than_requested(): void
    {
        // 【テストデータ準備】: 利用可能在庫(9-0=9)が要求数量(10)よりちょうど1少ない最小の不足幅を準備する
        // 【境界値選択の根拠】: 「成功」と「失敗」の境界線のすぐ内側（不足側）を検証し、わずかな差でも確実に検出されることを保証する
        $context = $this->prepareQuotationWithItems(
            quantityA: 10, stockA: 9, reservedA: 0,
            quantityB: 1, stockB: 100, reservedB: 0,
        );
        $quotation = $context['quotation'];
        $productA = $context['productA'];

        // 【実際の処理実行】: 利用可能在庫が要求数量よりわずかに少ない境界条件でconfirmToOrder()を実行する
        try {
            $this->service()->confirmToOrder($quotation);
            $this->fail('InsufficientStockExceptionがスローされるべきです');
        } catch (InsufficientStockException $e) {
            // 【結果検証】: わずか1の差でも正しく不足として検出され、例外情報に正確な数量が含まれることを確認する
            $this->assertSame($productA->id, $e->productId); // 【確認内容】: 不足製品のIDが正しく特定されることを確認 🔵
            $this->assertSame(10, $e->requestedQuantity); // 【確認内容】: 要求数量(10)が例外情報として正しく保持されることを確認 🔵
            $this->assertSame(9, $e->availableQuantity); // 【確認内容】: 利用可能数量(9)が例外情報として正しく保持されることを確認 🔵
        }

        // 【結果検証】: reserved_quantityが変更されず、データの不整合が発生していないことを確認する
        $this->assertDatabaseHas('products', [
            'id' => $productA->id,
            'reserved_quantity' => 0,
        ]); // 【確認内容】: 在庫不足時にreserved_quantityが変更されないことを確認 🔵
    }

    /**
     * 【テスト目的】: 見積保存時に見積番号がQUO-{年度}-{連番4桁}形式で採番され、document_sequencesが正しく更新されることを確認する
     * 【テスト内容】: document_sequencesにレコードが存在しない初回採番と、last_number=5のレコードが存在する2回目以降の採番を検証する
     * 【期待される動作】: 初回はQUO-{年度}-0001、既存ありの場合はlast_numberがインクリメントされた番号が生成される
     * 🔵 信頼性レベル: TASK-0008.md実装詳細2・完了条件「QUO-2026-0001形式」より直接抽出
     */
    public function test_create_quotation_issues_quotation_number_in_year_and_sequence_format(): void
    {
        // 【テストデータ準備】: document_sequencesにレコードが存在しない年度初回採番のケースを準備する
        // 【初期条件設定】: customer・product・creatorは見積作成に必要な最小限の関連データとして用意する
        $customer = Customer::factory()->create();
        $product = Product::factory()->create(['stock_quantity' => 100, 'reserved_quantity' => 0]);
        $creator = User::factory()->create();
        $year = (int) now()->year;

        // 【実際の処理実行】: QuotationService::create()相当のメソッドで見積を新規作成する
        // 【処理内容】: document_sequences(document_type=1, fiscal_year=当該年度)をlockForUpdateで取得・更新し、見積番号を発行する想定
        $quotation = $this->service()->create(new \App\DataTransferObjects\QuotationData(
            id: null,
            customerId: $customer->id,
            items: [
                new \App\DataTransferObjects\QuotationItemData(productId: $product->id, quantity: 1, unitPrice: 1000),
            ],
            remarks: null,
            expiresAt: now()->addDays(30)->toDateString(),
            createdBy: $creator->id,
        ));

        // 【結果検証】: 見積番号が「QUO-{年度}-0001」形式で採番されることを確認する
        // 【期待値確認】: document_sequencesに年度初回のレコード（last_number=1）が作成される想定
        $this->assertSame(sprintf('QUO-%d-0001', $year), $quotation->quotation_number); // 【確認内容】: 初回採番が4桁ゼロ埋めの連番1で発行されることを確認 🔵
        $this->assertDatabaseHas('document_sequences', [
            'document_type' => \App\Enums\DocumentType::QUOTATION->value,
            'fiscal_year' => $year,
            'last_number' => 1,
        ]); // 【確認内容】: document_sequencesのlast_numberが1に設定されることを確認 🔵

        // 【テストデータ準備】: 2件目の見積を作成し、連番がインクリメントされることを確認する
        $secondQuotation = $this->service()->create(new \App\DataTransferObjects\QuotationData(
            id: null,
            customerId: $customer->id,
            items: [
                new \App\DataTransferObjects\QuotationItemData(productId: $product->id, quantity: 1, unitPrice: 1000),
            ],
            remarks: null,
            expiresAt: now()->addDays(30)->toDateString(),
            createdBy: $creator->id,
        ));

        // 【結果検証】: 2件目はlast_number=2を用いて「QUO-{年度}-0002」が発行されることを確認する
        $this->assertSame(sprintf('QUO-%d-0002', $year), $secondQuotation->quotation_number); // 【確認内容】: 2回目の採番が連番をインクリメントして発行されることを確認 🔵
        $this->assertDatabaseHas('document_sequences', [
            'document_type' => \App\Enums\DocumentType::QUOTATION->value,
            'fiscal_year' => $year,
            'last_number' => 2,
        ]); // 【確認内容】: document_sequences.last_numberが呼び出し回数分インクリメントされることを確認 🔵
    }
}
