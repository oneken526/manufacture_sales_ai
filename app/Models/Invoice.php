<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 請求書モデル
 * 🔵 信頼性: database-schema.sql（invoicesテーブル）・TASK-0012より
 */
#[Fillable(['invoice_number', 'sales_order_id', 'total_amount', 'payment_status', 'invoice_pdf_path', 'issued_at', 'issued_by'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_status' => PaymentStatus::class,
            'issued_at' => 'datetime',
            'total_amount' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<SalesOrder, $this>
     */
    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
