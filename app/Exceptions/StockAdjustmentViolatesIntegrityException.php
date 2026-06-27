<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 在庫調整の結果が在庫整合性制約（stock_quantity >= 0、reserved_quantity <= stock_quantity）に
 * 違反する場合にスローされる例外
 * 🔵 信頼性: database-schema.sql（chk_products_stock, chk_products_reserved_le_stock制約）・TASK-0006.md実装詳細3より
 */
class StockAdjustmentViolatesIntegrityException extends RuntimeException
{
    public function __construct(public readonly int $productId, string $message)
    {
        parent::__construct($message);
    }
}
