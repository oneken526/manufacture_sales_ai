<?php

namespace App\Services;

use App\DataTransferObjects\SalesReportData;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 売上レポートサービス
 * 🔵 信頼性: REQ-080〜083, NFR-002・dataflow.md機能5より
 *
 * 集計はDB側のSQL集約クエリ（GROUP BY + SUM）に委譲し、
 * アプリケーション層でのループ集計を避けることで10秒以内（NFR-002）の応答時間を実現する。
 * キャンセル（5）・返品済み（6）の受注は集計対象から除外する。
 */
class ReportService
{
    /** @var array<int> */
    private array $excludedStatuses = [
        OrderStatus::CANCELLED->value,
        OrderStatus::RETURNED->value,
    ];

    /**
     * 月次の売上集計（日別）を返す。
     * 🔵 REQ-080・NFR-002より
     */
    public function aggregateMonthly(int $year, int $month): SalesReportData
    {
        $rows = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->whereYear('sales_orders.confirmed_at', $year)
            ->whereMonth('sales_orders.confirmed_at', $month)
            ->whereNotIn('sales_orders.status', $this->excludedStatuses)
            ->selectRaw('DATE(sales_orders.confirmed_at) as label, SUM(sales_order_items.quantity * sales_order_items.unit_price) as amount')
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'amount' => (int) $row->amount])
            ->toArray();

        $totalAmount = (int) array_sum(array_column($rows, 'amount'));

        return new SalesReportData(
            periodType: 'monthly',
            groupBy: 'period',
            rows: $rows,
            totalAmount: $totalAmount,
        );
    }

    /**
     * 年次の売上集計（月別）を返す。
     * 🔵 REQ-080より
     *
     * DB関数はドライバに応じて切り替える（NFR-030: MySQL/PostgreSQL/SQLite対応）。
     */
    public function aggregateYearly(int $year): SalesReportData
    {
        $yearMonthExpr = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y-%m', sales_orders.confirmed_at) as label",
            'pgsql' => "TO_CHAR(sales_orders.confirmed_at, 'YYYY-MM') as label",
            default => "DATE_FORMAT(sales_orders.confirmed_at, '%Y-%m') as label",
        };

        $rows = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->whereYear('sales_orders.confirmed_at', $year)
            ->whereNotIn('sales_orders.status', $this->excludedStatuses)
            ->selectRaw("{$yearMonthExpr}, SUM(sales_order_items.quantity * sales_order_items.unit_price) as amount")
            ->groupBy('label')
            ->orderBy('label')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'amount' => (int) $row->amount])
            ->toArray();

        $totalAmount = (int) array_sum(array_column($rows, 'amount'));

        return new SalesReportData(
            periodType: 'yearly',
            groupBy: 'period',
            rows: $rows,
            totalAmount: $totalAmount,
        );
    }

    /**
     * 顧客別売上ランキング（金額降順）を返す。
     * 🔵 REQ-081より
     */
    public function rankByCustomer(int $year, ?int $month = null): SalesReportData
    {
        $query = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->whereYear('sales_orders.confirmed_at', $year)
            ->whereNotIn('sales_orders.status', $this->excludedStatuses);

        if ($month !== null) {
            $query->whereMonth('sales_orders.confirmed_at', $month);
        }

        $rows = $query
            ->selectRaw('customers.company_name as label, SUM(sales_order_items.quantity * sales_order_items.unit_price) as amount')
            ->groupBy('customers.id', 'customers.company_name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'amount' => (int) $row->amount])
            ->toArray();

        $totalAmount = (int) array_sum(array_column($rows, 'amount'));

        return new SalesReportData(
            periodType: $month !== null ? 'monthly' : 'yearly',
            groupBy: 'customer',
            rows: $rows,
            totalAmount: $totalAmount,
        );
    }

    /**
     * 商品別売上ランキング（金額降順）を返す。
     * 🔵 REQ-082より
     */
    public function rankByProduct(int $year, ?int $month = null): SalesReportData
    {
        $query = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->join('products', 'sales_order_items.product_id', '=', 'products.id')
            ->whereYear('sales_orders.confirmed_at', $year)
            ->whereNotIn('sales_orders.status', $this->excludedStatuses);

        if ($month !== null) {
            $query->whereMonth('sales_orders.confirmed_at', $month);
        }

        $rows = $query
            ->selectRaw('products.product_name as label, SUM(sales_order_items.quantity * sales_order_items.unit_price) as amount')
            ->groupBy('products.id', 'products.product_name')
            ->orderByDesc('amount')
            ->get()
            ->map(fn ($row) => ['label' => $row->label, 'amount' => (int) $row->amount])
            ->toArray();

        $totalAmount = (int) array_sum(array_column($rows, 'amount'));

        return new SalesReportData(
            periodType: $month !== null ? 'monthly' : 'yearly',
            groupBy: 'product',
            rows: $rows,
            totalAmount: $totalAmount,
        );
    }

    /**
     * レポートデータをCSVストリームとして返す。
     * 🔵 REQ-083・dataflow.md機能5（ストリーミングダウンロード）より
     */
    public function exportCsv(SalesReportData $data, string $filename): StreamedResponse
    {
        $headers = match ($data->groupBy) {
            'customer' => ['顧客名', '売上金額（円）'],
            'product' => ['商品名', '売上金額（円）'],
            default => ['期間', '売上金額（円）'],
        };

        return response()->streamDownload(function () use ($data, $headers) {
            $stream = fopen('php://output', 'w');
            // BOM付きUTF-8（Excel文字化け対策）
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers);
            foreach ($data->rows as $row) {
                fputcsv($stream, [$row['label'], $row['amount']]);
            }
            fputcsv($stream, ['合計', $data->totalAmount]);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
