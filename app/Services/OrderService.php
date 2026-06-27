<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\StockMovementReason;
use App\Models\Product;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Repositories\Contracts\SalesOrderRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private readonly SalesOrderRepositoryInterface $orders,
    ) {
    }

    public function paginate(?int $status = null, int $perPage = 50): LengthAwarePaginator
    {
        return $this->orders->paginate($status, $perPage);
    }

    public function find(int $id): ?SalesOrder
    {
        return $this->orders->find($id);
    }

    /**
     * 受注をキャンセルする。
     *
     * DBトランザクション内で対象製品を lockForUpdate() で行ロックし、
     * reserved_quantity を減算して stock_movements を記録、受注を CANCELLED に更新する。
     *
     * @throws \InvalidArgumentException キャンセル不可ステータス（shipped以降）の場合
     * @throws \RuntimeException reserved_quantity が負値になる場合（データ不整合）
     */
    public function cancel(SalesOrder $order, ?string $reason = null): void
    {
        if (! $order->status->isCancellable()) {
            throw new \InvalidArgumentException(
                sprintf('ステータス「%s」の受注はキャンセルできません。', $order->status->label())
            );
        }

        DB::transaction(function () use ($order, $reason) {
            $order->load('items');

            foreach ($order->items as $item) {
                // lockForUpdate() で同一製品への並行キャンセルを防ぐ
                $product = Product::query()->whereKey($item->product_id)->lockForUpdate()->firstOrFail();

                $newReserved = $product->reserved_quantity - $item->quantity;
                if ($newReserved < 0) {
                    throw new \RuntimeException(
                        sprintf(
                            '製品ID %d の reserved_quantity が負値になります（現在: %d、減算量: %d）。',
                            $product->id,
                            $product->reserved_quantity,
                            $item->quantity,
                        )
                    );
                }

                $product->update(['reserved_quantity' => $newReserved]);

                StockMovement::query()->create([
                    'product_id' => $product->id,
                    'reason' => StockMovementReason::RESERVATION_RELEASE,
                    'quantity_change' => -$item->quantity,
                    'related_order_id' => $order->id,
                    'operated_by' => $order->created_by,
                    'memo' => sprintf('受注%sのキャンセルによる在庫引当解除', $order->order_number),
                    'created_at' => now(),
                ]);
            }

            $order->update([
                'status' => OrderStatus::CANCELLED,
                'cancelled_at' => now(),
            ]);
        });
    }

    /**
     * 出荷指示を発行する（CONFIRMED → SHIPPING_INSTRUCTED）。
     *
     * @throws \InvalidArgumentException CONFIRMED 以外のステータスの場合
     */
    public function issueShippingInstruction(SalesOrder $order): void
    {
        if ($order->status !== OrderStatus::CONFIRMED) {
            throw new \InvalidArgumentException(
                sprintf(
                    'ステータス「%s」の受注には出荷指示を発行できません。受注確定済みの受注のみ対象です。',
                    $order->status->label()
                )
            );
        }

        $order->update(['status' => OrderStatus::SHIPPING_INSTRUCTED]);
    }
}
