<?php

namespace App\DataTransferObjects;

/**
 * 製品DTO
 * 🔵 信頼性: design/manufacture-sales-system/data-types.php（ProductData定義）・database-schema.sql（productsテーブル）より
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
     * 🔵 信頼性: data-types.php（ProductData::availableQuantity()）より
     */
    public function availableQuantity(): int
    {
        return $this->stockQuantity - $this->reservedQuantity;
    }

    /**
     * 在庫数がアラート閾値を下回っているかどうか
     * 🟡 信頼性: REQ-022（在庫数が閾値を下回った場合の警告表示）より
     */
    public function isLowStock(): bool
    {
        return $this->stockQuantity < $this->alertThreshold;
    }

    /**
     * フォームリクエストのバリデーション済み配列からDTOを生成する
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes, ?int $id = null): self
    {
        return new self(
            id: $id,
            productCode: $attributes['product_code'],
            productName: $attributes['product_name'],
            unitPrice: (int) ($attributes['unit_price'] ?? 0),
            unit: $attributes['unit'] ?? '個',
            stockQuantity: (int) ($attributes['stock_quantity'] ?? 0),
            reservedQuantity: (int) ($attributes['reserved_quantity'] ?? 0),
            alertThreshold: (int) ($attributes['alert_threshold'] ?? 0),
        );
    }

    /**
     * Eloquentモデルへの永続化用に、DBカラム名をキーとした連想配列へ変換する
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_code' => $this->productCode,
            'product_name' => $this->productName,
            'unit_price' => $this->unitPrice,
            'unit' => $this->unit,
            'stock_quantity' => $this->stockQuantity,
            'alert_threshold' => $this->alertThreshold,
        ];
    }
}
