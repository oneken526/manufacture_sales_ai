<?php

namespace App\Enums;

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
