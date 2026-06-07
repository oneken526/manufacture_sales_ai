<?php

namespace Tests\Unit;

use App\Enums\DocumentType;
use App\Enums\OrderStatus;
use App\Enums\PaymentSource;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Enums\StockMovementReason;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

/**
 * TASK-0002 単体テスト要件 テストケース4 に対応する検証。
 * data-types.php に定義されたコード値・ラベルとの一致を確認する。
 *
 * @see docs/design/manufacture-sales-system/data-types.php
 */
class EnumsTest extends TestCase
{
    public function test_user_role_values_and_labels(): void
    {
        $this->assertSame(1, UserRole::ADMIN->value);
        $this->assertSame(2, UserRole::SALES->value);
        $this->assertSame(3, UserRole::WAREHOUSE->value);
        $this->assertSame(4, UserRole::ACCOUNTING->value);

        $this->assertSame(UserRole::ADMIN, UserRole::from(1));

        $this->assertSame('システム管理者', UserRole::ADMIN->label());
        $this->assertSame('営業担当者', UserRole::SALES->label());
        $this->assertSame('在庫・出荷担当者', UserRole::WAREHOUSE->label());
        $this->assertSame('管理職・経理担当', UserRole::ACCOUNTING->label());
    }

    public function test_quotation_status_values(): void
    {
        $this->assertSame(1, QuotationStatus::DRAFT->value);
        $this->assertSame(2, QuotationStatus::CONVERTED->value);
        $this->assertSame(3, QuotationStatus::CANCELLED->value);
        $this->assertSame(4, QuotationStatus::EXPIRED->value);

        $this->assertSame(QuotationStatus::CONVERTED, QuotationStatus::from(2));
    }

    public function test_order_status_values_and_labels(): void
    {
        $this->assertSame(1, OrderStatus::CONFIRMED->value);
        $this->assertSame(2, OrderStatus::SHIPPING_INSTRUCTED->value);
        $this->assertSame(3, OrderStatus::SHIPPED->value);
        $this->assertSame(4, OrderStatus::INVOICED->value);
        $this->assertSame(5, OrderStatus::CANCELLED->value);
        $this->assertSame(6, OrderStatus::RETURNED->value);

        $this->assertSame(OrderStatus::SHIPPED, OrderStatus::from(3));

        $this->assertSame('受注確定', OrderStatus::CONFIRMED->label());
        $this->assertSame('出荷指示済み', OrderStatus::SHIPPING_INSTRUCTED->label());
        $this->assertSame('出荷完了', OrderStatus::SHIPPED->label());
        $this->assertSame('請求済み', OrderStatus::INVOICED->label());
        $this->assertSame('キャンセル', OrderStatus::CANCELLED->label());
        $this->assertSame('返品済み', OrderStatus::RETURNED->label());
    }

    public function test_payment_status_values(): void
    {
        $this->assertSame(1, PaymentStatus::UNPAID->value);
        $this->assertSame(2, PaymentStatus::PARTIALLY_PAID->value);
        $this->assertSame(3, PaymentStatus::PAID->value);

        $this->assertSame(PaymentStatus::PAID, PaymentStatus::from(3));
    }

    public function test_payment_source_values(): void
    {
        $this->assertSame(1, PaymentSource::MANUAL->value);
        $this->assertSame(2, PaymentSource::CSV_IMPORT->value);

        $this->assertSame(PaymentSource::CSV_IMPORT, PaymentSource::from(2));
    }

    public function test_stock_movement_reason_values(): void
    {
        $this->assertSame(1, StockMovementReason::RESERVATION->value);
        $this->assertSame(2, StockMovementReason::RESERVATION_RELEASE->value);
        $this->assertSame(3, StockMovementReason::SHIPMENT->value);
        $this->assertSame(4, StockMovementReason::RETURN_RECEIVED->value);
        $this->assertSame(5, StockMovementReason::MANUAL_ADJUSTMENT->value);

        $this->assertSame(StockMovementReason::SHIPMENT, StockMovementReason::from(3));
    }

    public function test_document_type_values(): void
    {
        $this->assertSame(1, DocumentType::QUOTATION->value);
        $this->assertSame(2, DocumentType::ORDER->value);
        $this->assertSame(3, DocumentType::INVOICE->value);

        $this->assertSame(DocumentType::INVOICE, DocumentType::from(3));
    }

    public function test_all_enums_are_int_backed_with_expected_case_counts(): void
    {
        $this->assertCount(4, UserRole::cases());
        $this->assertCount(4, QuotationStatus::cases());
        $this->assertCount(6, OrderStatus::cases());
        $this->assertCount(3, PaymentStatus::cases());
        $this->assertCount(2, PaymentSource::cases());
        $this->assertCount(5, StockMovementReason::cases());
        $this->assertCount(3, DocumentType::cases());
    }
}
