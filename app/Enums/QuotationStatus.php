<?php

namespace App\Enums;

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
