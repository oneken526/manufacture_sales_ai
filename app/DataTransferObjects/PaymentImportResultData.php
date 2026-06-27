<?php

namespace App\DataTransferObjects;

/**
 * 振込CSVインポート結果DTO
 * 🔵 信頼性: data-types.php（PaymentImportResultData）・TASK-0013より
 */
readonly class PaymentImportResultData
{
    /**
     * @param  array<int, array{row: string, reason: string}>  $unmatchedItems
     */
    public function __construct(
        public int $matchedCount,
        public int $unmatchedCount,
        public array $unmatchedItems,
    ) {
    }
}
