<?php

namespace App\DataTransferObjects;

/**
 * 顧客DTO
 * 🔵 信頼性: 要件定義REQ-010・DBスキーマ・design/manufacture-sales-system/data-types.phpより
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

    /**
     * フォームリクエストのバリデーション済み配列からDTOを生成する
     *
     * @param array<string, mixed> $attributes
     */
    public static function fromArray(array $attributes, ?int $id = null): self
    {
        return new self(
            id: $id,
            companyName: $attributes['company_name'],
            contactName: $attributes['contact_name'] ?? null,
            address: $attributes['address'] ?? null,
            phone: $attributes['phone'] ?? null,
            email: $attributes['email'] ?? null,
            creditLimit: (int) ($attributes['credit_limit'] ?? 0),
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
            'company_name' => $this->companyName,
            'contact_name' => $this->contactName,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'credit_limit' => $this->creditLimit,
        ];
    }
}
