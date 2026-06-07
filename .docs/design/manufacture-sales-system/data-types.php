<?php

/**
 * 製造業向け販売管理システム データ型定義（PHP）
 *
 * 作成日: 2026-06-07
 * 関連設計: architecture.md
 *
 * 信頼性レベル:
 * - 🔵 青信号: 要件定義書・設計ヒアリング・DBスキーマを参考にした確実な型定義
 * - 🟡 黄信号: 要件定義書・設計ヒアリングから妥当な推測による型定義
 * - 🔴 赤信号: 要件定義書・設計ヒアリングにない推測による型定義
 *
 * 本プロジェクトはLaravel(PHP)のため、TypeScript interfaceの代わりに
 * PHP 8.1+ の Enum と DTO（Data Transfer Object）クラスとして定義する。
 * 実装時は app/Enums/, app/DataTransferObjects/ 配下に配置する想定。
 */

namespace App\Enums;

// ========================================
// 列挙型（Enum）
// ========================================

/**
 * ユーザー役割
 * 🔵 信頼性: 要件定義REQ-002・設計ヒアリングより
 *
 * 区分値はDB上では数値コード（TINYINT）で保持する（database-schema.sqlのコード表と対応）。
 */
enum UserRole: int
{
    case ADMIN = 1;                 // システム管理者 🔵
    case SALES = 2;                 // 営業担当者 🔵
    case WAREHOUSE = 3;             // 在庫・出荷担当者 🔵
    case ACCOUNTING = 4;            // 管理職・経理担当 🔵

    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'システム管理者',
            self::SALES => '営業担当者',
            self::WAREHOUSE => '在庫・出荷担当者',
            self::ACCOUNTING => '管理職・経理担当',
        };
    }
}

/**
 * 見積ステータス
 * 🔵 信頼性: 要件定義REQ-031・database-schema.sqlコード表より
 */
enum QuotationStatus: int
{
    case DRAFT = 1;       // 見積中 🔵
    case CONVERTED = 2;   // 受注転換済み 🔵
    case CANCELLED = 3;   // キャンセル 🔵
    case EXPIRED = 4;     // 期限切れ 🔵
}

/**
 * 受注ステータス
 * 🔵 信頼性: 要件定義REQ-031, REQ-040〜053・note.md受注ステータスフローより
 *
 * 区分値はDB上では数値コード（TINYINT）で保持する（database-schema.sqlのコード表と対応）。
 */
enum OrderStatus: int
{
    case CONFIRMED = 1;            // 受注確定（在庫引当済み） 🔵
    case SHIPPING_INSTRUCTED = 2;  // 出荷指示済み 🔵
    case SHIPPED = 3;              // 出荷完了 🔵
    case INVOICED = 4;             // 請求済み 🔵
    case CANCELLED = 5;            // キャンセル 🔵
    case RETURNED = 6;             // 返品済み 🔵

    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED => '受注確定',
            self::SHIPPING_INSTRUCTED => '出荷指示済み',
            self::SHIPPED => '出荷完了',
            self::INVOICED => '請求済み',
            self::CANCELLED => 'キャンセル',
            self::RETURNED => '返品済み',
        };
    }
}

/**
 * 入金ステータス
 * 🔵 信頼性: 要件定義REQ-062より
 *
 * 区分値はDB上では数値コード（TINYINT）で保持する（database-schema.sqlのコード表と対応）。
 */
enum PaymentStatus: int
{
    case UNPAID = 1;           // 未入金 🔵
    case PARTIALLY_PAID = 2;   // 部分入金 🔵
    case PAID = 3;             // 入金済み 🔵
}

/**
 * 入金記録の登録元
 * 🔵 信頼性: 要件定義REQ-063・database-schema.sqlコード表より
 */
enum PaymentSource: int
{
    case MANUAL = 1;       // 手動登録 🔵
    case CSV_IMPORT = 2;   // CSV取込照合 🔵
}

/**
 * 在庫変動理由
 * 🟡 信頼性: REQ-071, REQ-072から妥当な推測
 *
 * 区分値はDB上では数値コード（TINYINT）で保持する（database-schema.sqlのコード表と対応）。
 */
enum StockMovementReason: int
{
    case RESERVATION = 1;          // 受注引き当て 🟡
    case RESERVATION_RELEASE = 2;  // 引き当て解除（キャンセル） 🟡
    case SHIPMENT = 3;             // 出荷による減算 🟡
    case RETURN_RECEIVED = 4;      // 返品による加算 🟡
    case MANUAL_ADJUSTMENT = 5;    // 手動調整 🟡
}

/**
 * 採番対象ドキュメント種別
 * 🔵 信頼性: 設計ヒアリングQ5（年度+連番ルール）・database-schema.sqlコード表より
 */
enum DocumentType: int
{
    case QUOTATION = 1;   // 見積書 🔵
    case ORDER = 2;       // 受注 🔵
    case INVOICE = 3;     // 請求書 🔵
}

namespace App\DataTransferObjects;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\UserRole;

// ========================================
// DTO定義（リクエスト/レスポンスの型）
// ========================================

/**
 * 顧客DTO
 * 🔵 信頼性: 要件定義REQ-010・DBスキーマより
 */
final class CustomerData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $companyName,     // 会社名 🔵
        public readonly ?string $contactName,    // 担当者名 🔵
        public readonly ?string $address,        // 住所 🔵
        public readonly ?string $phone,          // 電話 🔵
        public readonly ?string $email,          // メール 🔵
        public readonly int $creditLimit,        // 与信枠 🔵
    ) {
    }
}

/**
 * 製品DTO
 * 🔵 信頼性: 要件定義REQ-020・設計ヒアリングQ6より
 */
final class ProductData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $productCode,     // 品番 🔵
        public readonly string $productName,     // 製品名 🔵
        public readonly int $unitPrice,          // 単価 🔵
        public readonly string $unit,            // 単位 🔵
        public readonly int $stockQuantity,      // 実在庫数 🔵
        public readonly int $reservedQuantity,   // 引当中在庫数 🔵
        public readonly int $alertThreshold,     // 在庫アラート閾値 🟡
    ) {
    }

    /**
     * 利用可能在庫数 = 実在庫 - 引当中
     * 🔵 信頼性: 設計ヒアリングQ6より
     */
    public function availableQuantity(): int
    {
        return $this->stockQuantity - $this->reservedQuantity;
    }
}

/**
 * 見積DTO
 * 🔵 信頼性: 要件定義REQ-030より
 */
final class QuotationData
{
    /** @param QuotationItemData[] $items */
    public function __construct(
        public readonly ?int $id,
        public readonly string $quotationNumber,  // 見積番号（年度+連番） 🔵
        public readonly int $customerId,
        public readonly array $items,
        public readonly ?string $remarks,         // 備考 🔵
        public readonly ?string $expiresAt,       // 有効期限 🟡
    ) {
    }
}

/**
 * 見積明細DTO
 * 🔵 信頼性: 要件定義REQ-030より
 */
final class QuotationItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly int $unitPrice,
    ) {
    }
}

/**
 * 受注DTO
 * 🔵 信頼性: 要件定義REQ-040〜043・設計ヒアリングQ5より
 */
final class SalesOrderData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $orderNumber,      // 受注番号（年度+連番 例: ORD-2026-0001） 🔵
        public readonly int $customerId,
        public readonly OrderStatus $status,
        public readonly ?int $quotationId,
    ) {
    }
}

/**
 * 出荷DTO
 * 🔵 信頼性: 要件定義REQ-050〜053より
 */
final class ShipmentData
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $salesOrderId,
        public readonly ?string $shippedAt,        // 出荷完了日時 🔵
        public readonly ?string $deliveryNotePath,  // 納品書PDFパス 🔵
    ) {
    }
}

/**
 * 請求書DTO
 * 🔵 信頼性: 要件定義REQ-060〜064・設計ヒアリングQ5より
 */
final class InvoiceData
{
    public function __construct(
        public readonly ?int $id,
        public readonly string $invoiceNumber,    // 請求書番号（例: INV-2026-0001） 🔵
        public readonly int $salesOrderId,
        public readonly int $totalAmount,         // 請求金額 🔵
        public readonly PaymentStatus $paymentStatus,
        public readonly ?string $invoicePdfPath,
    ) {
    }
}

/**
 * 振込データインポート結果DTO
 * 🔵 信頼性: 要件定義REQ-063, EDGE-002より
 */
final class PaymentImportResultData
{
    /**
     * @param array<int, array{invoiceNumber: string, amount: int, reason: string}> $unmatchedItems
     */
    public function __construct(
        public readonly int $matchedCount,    // 照合成功件数 🔵
        public readonly int $unmatchedCount,  // 未照合件数 🔵
        public readonly array $unmatchedItems,
    ) {
    }
}

/**
 * 売上レポート集計結果DTO
 * 🔵 信頼性: 要件定義REQ-080〜083より
 */
final class SalesReportData
{
    /**
     * @param array<int, array{label: string, amount: int}> $rows 集計行（顧客名/商品名/年月 + 金額）
     */
    public function __construct(
        public readonly string $periodType,  // 'monthly' | 'yearly' 🔵
        public readonly string $groupBy,     // 'customer' | 'product' | 'period' 🔵
        public readonly array $rows,
        public readonly int $totalAmount,
    ) {
    }
}

/**
 * APIレスポンス共通型
 * 🟡 信頼性: 既存実装の共通パターンから妥当な推測
 *
 * @template T
 */
final class ApiResponseData
{
    public function __construct(
        public readonly bool $success,
        public readonly mixed $data = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorMessage = null,
    ) {
    }
}

// ========================================
// 信頼性レベルサマリー
// ========================================
/**
 * - 🔵 青信号: 11件 (85%)
 * - 🟡 黄信号: 2件 (15%)
 * - 🔴 赤信号: 0件 (0%)
 *
 * 品質評価: 高品質
 */
