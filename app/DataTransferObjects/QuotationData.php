<?php

namespace App\DataTransferObjects;

/**
 * 見積DTO
 * 🔵 信頼性: TASK-0008.md実装詳細1・テストコード
 *           （QuotationData(id, customerId, items, remarks, expiresAt, createdBy)）より
 */
final class QuotationData
{
    /**
     * @param array<int, QuotationItemData> $items
     */
    public function __construct(
        public readonly ?int $id,
        public readonly int $customerId,
        public readonly array $items,
        public readonly ?string $remarks,
        public readonly ?string $expiresAt,
        public readonly int $createdBy,
    ) {
    }

    /**
     * フォームリクエストのバリデーション済み配列からDTOを生成する
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes, int $createdBy, ?int $id = null): self
    {
        return new self(
            id: $id,
            customerId: (int) $attributes['customer_id'],
            items: array_map(
                fn (array $item) => QuotationItemData::fromArray($item),
                $attributes['items'] ?? [],
            ),
            remarks: $attributes['remarks'] ?? null,
            expiresAt: $attributes['expires_at'] ?? null,
            createdBy: $createdBy,
        );
    }
}
