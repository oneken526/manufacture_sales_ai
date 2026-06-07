<?php

namespace App\Enums;

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
