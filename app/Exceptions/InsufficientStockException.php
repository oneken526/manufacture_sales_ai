<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 受注確定時に在庫が不足している場合にスローされる例外
 * 🔵 信頼性: EDGE-001「在庫不足の場合、システムは受注確定処理を中止し、不足している製品名と不足数量を示す警告を表示しなければならない」より
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requestedQuantity,
        public readonly int $availableQuantity,
    ) {
        parent::__construct('在庫が不足しています');
    }
}
