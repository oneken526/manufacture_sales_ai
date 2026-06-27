<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\SalesOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 受注モデル
 * 🟡 信頼性: database-schema.sql（sales_ordersテーブル定義）より、
 *           本タスク（TASK-0005）の顧客詳細・削除制限機能で必要な最小限の構成として実装する
 *           （詳細なリレーション・業務ロジックはTASK-0009で拡充予定）
 */
#[Fillable(['order_number', 'quotation_id', 'customer_id', 'status', 'confirmed_at', 'cancelled_at', 'created_by'])]
class SalesOrder extends Model
{
    /** @use HasFactory<SalesOrderFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 受注の元となった見積（見積からの転換でない場合はnull）
     * 🔵 信頼性: TASK-0008.md実装詳細5「sales_orders（quotation_id=元見積ID）を作成する」より
     *
     * @return BelongsTo<Quotation, $this>
     */
    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    /**
     * @return HasMany<SalesOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class);
    }
}
