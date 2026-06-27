<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 売上レポートコントローラ
 * 🔵 信頼性: api-endpoints.md（GET /reports/sales, GET /reports/sales/export）・REQ-084より
 */
class ReportController extends Controller
{
    public function __construct(
        private readonly ReportService $reportService,
    ) {
    }

    /**
     * 売上レポート画面を表示する。
     *
     * クエリパラメータ:
     *   period: 'monthly'|'yearly' (default: 'monthly')
     *   year:   int (default: current year)
     *   month:  int (default: current month, period=monthly時のみ有効)
     *   group:  'customer'|'product'|'period' (default: 'customer')
     */
    public function index(Request $request): View
    {
        $period = $request->query('period', 'monthly');
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $group = $request->query('group', 'customer');

        $report = $this->resolve($period, $year, $month, $group);

        $chartData = [
            'labels' => array_column($report->rows, 'label'),
            'amounts' => array_column($report->rows, 'amount'),
        ];

        return view('reports.sales', compact('report', 'chartData', 'period', 'year', 'month', 'group'));
    }

    /**
     * 売上レポートをCSVエクスポートする。
     * 🔵 REQ-083・dataflow.md機能5（ストリーミングダウンロード）より
     */
    public function export(Request $request): StreamedResponse
    {
        $period = $request->query('period', 'monthly');
        $year = (int) $request->query('year', now()->year);
        $month = (int) $request->query('month', now()->month);
        $group = $request->query('group', 'customer');

        $report = $this->resolve($period, $year, $month, $group);

        $label = $period === 'yearly' ? $year : sprintf('%d-%02d', $year, $month);
        $filename = sprintf('sales_report_%s_%s.csv', $label, $group);

        return $this->reportService->exportCsv($report, $filename);
    }

    /**
     * リクエストパラメータに基づいてレポートデータを生成する。
     */
    private function resolve(string $period, int $year, int $month, string $group): \App\DataTransferObjects\SalesReportData
    {
        if ($period === 'yearly') {
            return match ($group) {
                'product' => $this->reportService->rankByProduct($year),
                'period' => $this->reportService->aggregateYearly($year),
                default => $this->reportService->rankByCustomer($year),
            };
        }

        return match ($group) {
            'product' => $this->reportService->rankByProduct($year, $month),
            'period' => $this->reportService->aggregateMonthly($year, $month),
            default => $this->reportService->rankByCustomer($year, $month),
        };
    }
}
