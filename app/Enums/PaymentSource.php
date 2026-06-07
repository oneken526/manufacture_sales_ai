<?php

namespace App\Enums;

/**
 * 入金記録の登録元
 * 🔵 信頼性: 要件定義REQ-063・database-schema.sqlコード表より
 */
enum PaymentSource: int
{
    case MANUAL = 1;       // 手動登録 🔵
    case CSV_IMPORT = 2;   // CSV取込照合 🔵
}
