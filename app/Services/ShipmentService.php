<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\Shipment;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;

/**
 * 出荷管理サービス
 * 🔵 信頼性: dataflow.md機能2シーケンス図・REQ-051〜053・REQ-072より
 */
class ShipmentService
{
    /**
     * 出荷完了処理。
     *
     * DBトランザクション内でstock_quantity/reserved_quantityを同時に減算し、
     * stock_movements記録・shipments作成・受注ステータスをSHIPPEDに更新する。
     * PDF生成はトランザクション外（コミット後）で実行する（dataflow.md機能2参照）。
     *
     * @throws \InvalidArgumentException 出荷指示済み以外のステータスの場合
     * @throws \RuntimeException 在庫が不足している場合
     */
    public function complete(SalesOrder $order, int $userId): Shipment
    {
        if ($order->status !== OrderStatus::SHIPPING_INSTRUCTED) {
            throw new \InvalidArgumentException(
                sprintf('ステータス「%s」の受注は出荷完了登録できません。', $order->status->label())
            );
        }

        $shipment = DB::transaction(function () use ($order, $userId) {
            $order->load('items');

            foreach ($order->items as $item) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

                $newStock = $product->stock_quantity - $item->quantity;
                $newReserved = $product->reserved_quantity - $item->quantity;

                if ($newStock < 0 || $newReserved < 0) {
                    throw new \RuntimeException(
                        sprintf(
                            '製品ID %d の在庫が不足しています（実在庫: %d、引当: %d、出荷数: %d）。',
                            $product->id,
                            $product->stock_quantity,
                            $product->reserved_quantity,
                            $item->quantity,
                        )
                    );
                }

                $product->update([
                    'stock_quantity' => $newStock,
                    'reserved_quantity' => $newReserved,
                ]);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'reason' => StockMovementReason::SHIPMENT,
                    'quantity_change' => -$item->quantity,
                    'related_order_id' => $order->id,
                    'operated_by' => $userId,
                    'memo' => sprintf('受注%sの出荷完了による在庫減算', $order->order_number),
                    'created_at' => now(),
                ]);
            }

            $shipment = Shipment::query()->create([
                'sales_order_id' => $order->id,
                'shipped_at' => now(),
                'shipped_by' => $userId,
            ]);

            $order->update(['status' => OrderStatus::SHIPPED]);

            return $shipment;
        });

        return $shipment;
    }

    /**
     * 返品処理。
     *
     * DBトランザクション内でstock_quantityを加算し、
     * stock_movements記録・受注ステータスをRETURNEDに更新する。
     *
     * @throws \InvalidArgumentException 出荷完了以外のステータスの場合
     */
    public function processReturn(Shipment $shipment, string $returnReason, int $userId): void
    {
        $order = $shipment->salesOrder;

        if ($order->status !== OrderStatus::SHIPPED) {
            throw new \InvalidArgumentException(
                sprintf('ステータス「%s」の受注は返品登録できません。', $order->status->label())
            );
        }

        DB::transaction(function () use ($shipment, $order, $returnReason, $userId) {
            $order->load('items');

            foreach ($order->items as $item) {
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

                $product->update([
                    'stock_quantity' => $product->stock_quantity + $item->quantity,
                ]);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'reason' => StockMovementReason::RETURN_RECEIVED,
                    'quantity_change' => $item->quantity,
                    'related_order_id' => $order->id,
                    'operated_by' => $userId,
                    'memo' => sprintf('受注%sの返品による在庫加算', $order->order_number),
                    'created_at' => now(),
                ]);
            }

            $shipment->update([
                'returned_at' => now(),
                'return_reason' => $returnReason,
            ]);

            $order->update(['status' => OrderStatus::RETURNED]);
        });
    }
}
