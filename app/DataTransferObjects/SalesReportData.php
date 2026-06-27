<?php

namespace App\DataTransferObjects;

/**
 * 売上レポート集計結果DTO
 * 🔵 信頼性: data-types.php（SalesReportData）・TASK-0014より
 */
readonly class SalesReportData
{
    /**
     * @param  array<int, array{label: string, amount: int}>  $rows  集計行（顧客名/商品名/年月 + 金額）
     */
    public function __construct(
        public string $periodType,  // 'monthly' | 'yearly'
        public string $groupBy,     // 'customer' | 'product' | 'period'
        public array $rows,
        public int $totalAmount,
    ) {
    }
}
