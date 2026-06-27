<?php

namespace App\DataTransferObjects;

/**
 * 見積明細DTO
 * 🔵 信頼性: TASK-0008.md実装詳細1・テストコード（QuotationItemData(productId, quantity, unitPrice)）より
 */
final class QuotationItemData
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly int $unitPrice,
    ) {
    }

    /**
     * フォームリクエストのバリデーション済み配列からDTOを生成する
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes): self
    {
        return new self(
            productId: (int) $attributes['product_id'],
            quantity: (int) $attributes['quantity'],
            unitPrice: (int) $attributes['unit_price'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'quantity' => $this->quantity,
            'unit_price' => $this->unitPrice,
        ];
    }
}
