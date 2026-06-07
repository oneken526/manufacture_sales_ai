<?php

namespace App\Enums;

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
