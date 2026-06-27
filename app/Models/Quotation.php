<?php

namespace App\Models;

use App\Enums\QuotationStatus;
use Database\Factories\QuotationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 見積モデル
 * 🔵 信頼性: database-schema.sql（quotationsテーブル定義）・TASK-0008.md実装詳細1より
 */
#[Fillable(['quotation_number', 'customer_id', 'status', 'remarks', 'expires_at', 'created_by'])]
class Quotation extends Model
{
    /** @use HasFactory<QuotationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuotationStatus::class,
            'expires_at' => 'date',
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
     * @return HasMany<QuotationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    /**
     * 受注転換後に紐づく受注（1見積につき高々1件）
     * 🔵 信頼性: TASK-0008.md実装詳細5「confirmToOrder()によりsales_orders（quotation_id=元見積ID）を作成する」より
     *
     * @return HasOne<SalesOrder, $this>
     */
    public function salesOrder(): HasOne
    {
        return $this->hasOne(SalesOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
