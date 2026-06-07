<?php

namespace App\Enums;

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
