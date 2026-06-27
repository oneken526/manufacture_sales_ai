<?php

namespace App\Http\Controllers;

use App\Services\PaymentImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 振込データCSVインポートコントローラ
 * 🔵 信頼性: api-endpoints.md（GET/POST /payments/import）・REQ-063より
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentImportService $importService,
    ) {
    }

    /**
     * インポート画面表示
     */
    public function index(): View
    {
        return view('payments.import');
    }

    /**
     * CSVアップロードと照合処理
     */
    public function import(Request $request): View
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $result = $this->importService->importBankCsv(
            $request->file('csv_file'),
            $request->user()->id
        );

        return view('payments.import_result', compact('result'));
    }
}
