<?php

namespace App\Services;

use App\DataTransferObjects\QuotationData;
use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\StockMovementReason;
use App\Exceptions\InsufficientStockException;
use App\Models\DocumentSequence;
use App\Models\Product;
use App\Models\Quotation;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Repositories\Contracts\QuotationRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 見積管理の業務ロジックを集約するサービスクラス
 * 🔵 信頼性: architecture.md（Controller → Service → Repository）・TASK-0008.md実装詳細2・5より
 */
class QuotationService
{
    public function __construct(
        private readonly QuotationRepositoryInterface $quotations,
    ) {
    }

    public function paginate(int $perPage = 50): LengthAwarePaginator
    {
        return $this->quotations->paginate($perPage);
    }

    public function find(int $id): ?Quotation
    {
        return $this->quotations->find($id);
    }

    /**
     * 見積番号を採番した上で見積（および明細）を新規作成する
     * 🔵 信頼性: TASK-0008.md実装詳細2「QUO-{年度}-{連番4桁}形式で見積番号を発行する」より
     */
    public function create(QuotationData $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $year = (int) now()->year;
            $quotationNumber = $this->quotations->issueQuotationNumber($year);

            return $this->quotations->create($data, $quotationNumber);
        });
    }

    /**
     * 見積を受注へ転換する（在庫引当・受注作成・ステータス更新をアトミックに実行）
     *
     * DBトランザクション内で対象製品を行ロック（lockForUpdate）した上で利用可能在庫
     * （stock_quantity - reserved_quantity）が明細数量を満たすか検証し、不足する製品が
     * 一つでもあればInsufficientStockExceptionをスローしてロールバックする（EDGE-001）。
     * 充足している場合はreserved_quantityの加算・stock_movements(reason=RESERVATION)の記録・
     * sales_orders/sales_order_itemsの作成・quotations.statusの更新を行う。
     * 🔵 信頼性レベル: TASK-0008.md単体テスト要件テストケース1・REQ-031・REQ-040・dataflow.md「機能1」
     *               ・ProductRepository::adjustStock()の悲観的ロックパターンより
     *
     * @throws InsufficientStockException 明細のいずれかで利用可能在庫が要求数量を下回る場合
     */
    public function confirmToOrder(Quotation $quotation): void
    {
        DB::transaction(function () use ($quotation) {
            $locked = Quotation::query()->whereKey($quotation->id)->lockForUpdate()->firstOrFail();
            $locked->load('items');

            $lockedProducts = $this->lockProductsWithSufficientStock($locked);
            $this->reserveStockForItems($locked, $lockedProducts);
            $this->createConfirmedSalesOrder($locked);

            // 【ステータス更新】: 見積を受注転換済みに変更する
            $locked->update(['status' => QuotationStatus::CONVERTED]);
        });
    }

    /**
     * 見積の全明細について対象製品を行ロックし、利用可能在庫が要求数量を満たすか検証する
     *
     * 不足する製品が一つでもあれば即座に例外をスローし、呼び出し元のトランザクションを
     * ロールバックさせる（EDGE-001）。問題なければ後続処理で再利用するため、
     * ロック済みの製品をquotation_item.idをキーとした連想配列で返す。
     * 🔵 信頼性レベル: TASK-0008.md単体テスト要件テストケース1・EDGE-001・ProductRepository::adjustStock()の悲観的ロックパターンより
     *
     * @return array<int, Product> quotation_item.id => ロック済みProduct
     *
     * @throws InsufficientStockException 利用可能在庫が要求数量を下回る製品がある場合
     */
    private function lockProductsWithSufficientStock(Quotation $locked): array
    {
        $lockedProducts = [];

        foreach ($locked->items as $item) {
            $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();
            $available = $product->stock_quantity - $product->reserved_quantity;

            if ($available < $item->quantity) {
                throw new InsufficientStockException($product->id, $item->quantity, $available);
            }

            $lockedProducts[$item->id] = $product;
        }

        return $lockedProducts;
    }

    /**
     * 在庫充足が確認済みの明細について、reserved_quantityを加算し在庫変動履歴を記録する
     *
     * @param  array<int, Product>  $lockedProducts  quotation_item.id => ロック済みProduct
     * 🔵 信頼性レベル: TASK-0008.md実装詳細5・REQ-040「在庫引当によりreserved_quantityを加算する」より
     */
    private function reserveStockForItems(Quotation $locked, array $lockedProducts): void
    {
        foreach ($locked->items as $item) {
            $product = $lockedProducts[$item->id];
            $product->update(['reserved_quantity' => $product->reserved_quantity + $item->quantity]);

            StockMovement::query()->create([
                'product_id' => $product->id,
                'reason' => StockMovementReason::RESERVATION,
                'quantity_change' => $item->quantity,
                'related_order_id' => null,
                'operated_by' => $locked->created_by,
                'memo' => sprintf('見積%sの受注確定による在庫引当', $locked->quotation_number),
                'created_at' => now(),
            ]);
        }
    }

    /**
     * 見積明細を引き継いだ確定済み受注（sales_orders/sales_order_items）を作成する
     * 🔵 信頼性レベル: TASK-0008.md実装詳細5「見積から受注（sales_orders/sales_order_items）を作成する」より
     */
    private function createConfirmedSalesOrder(Quotation $locked): SalesOrder
    {
        $salesOrder = SalesOrder::query()->create([
            'order_number' => $this->issueOrderNumber((int) now()->year),
            'quotation_id' => $locked->id,
            'customer_id' => $locked->customer_id,
            'status' => OrderStatus::CONFIRMED,
            'confirmed_at' => now(),
            'cancelled_at' => null,
            'created_by' => $locked->created_by,
        ]);

        foreach ($locked->items as $item) {
            $salesOrder->items()->create([
                'product_id' => $item->product_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]);
        }

        return $salesOrder;
    }

    /**
     * 受注番号を採番する（document_sequencesをDocumentType::ORDERで管理する）
     *
     * 採番処理そのものは DocumentSequence::issueNextNumber() に集約されており、
     * ここでは受注番号特有のフォーマット（ORD-{年度}-{連番4桁}）への変換のみを担う
     * （見積番号採番とのロジック重複をRefactorフェーズで解消）。
     * 🟡 信頼性: 見積番号採番（issueQuotationNumber）と同様の方針を受注番号にも適用する妥当な推測
     */
    private function issueOrderNumber(int $year): string
    {
        $nextNumber = DocumentSequence::issueNextNumber(DocumentType::ORDER, $year);

        return sprintf('ORD-%d-%04d', $year, $nextNumber);
    }
}
