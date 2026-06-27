@extends('pdf.layouts.base')

{{--
    【機能概要】: 見積書PDFテンプレート（QuotationPdf）
    【実装方針】: 全帳票共通レイアウト（pdf.layouts.base）を継承し、見積番号・宛先・有効期限・
                 明細一覧・合計金額を表示する。PdfService::download()経由でレンダリングされる。
    【テスト対応】: TC4（PDFプレビュー・ダウンロードでContent-Type: application/pdfが返ること）
    🔵 信頼性レベル: TASK-0008.md実装詳細4「QuotationPdfテンプレートで見積番号・宛先・明細・合計金額...を出力する」より
--}}
@section('content')
    <h1>見積書</h1>

    <table class="pdf-info-table" style="border:none; margin-bottom: 16px;">
        <tr>
            <td style="border:none;">見積番号: {{ $quotation->quotation_number }}</td>
            <td style="border:none; text-align:right;">発行日: {{ $quotation->created_at?->format('Y年m月d日') }}</td>
        </tr>
        <tr>
            <td style="border:none;">{{ $quotation->customer->company_name }} 御中</td>
            <td style="border:none; text-align:right;">
                有効期限: {{ optional($quotation->expires_at)->format('Y年m月d日') ?? '—' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>製品名</th>
                <th>数量</th>
                <th>単価</th>
                <th>金額</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $item)
                <tr>
                    <td>{{ $item->product->product_name }}</td>
                    <td style="text-align:right;">{{ number_format($item->quantity) }}</td>
                    <td style="text-align:right;">{{ number_format($item->unit_price) }}</td>
                    <td style="text-align:right;">{{ number_format($item->quantity * $item->unit_price) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align:right;">合計金額</td>
                <td style="text-align:right;">
                    {{ number_format($quotation->items->sum(fn ($item) => $item->quantity * $item->unit_price)) }}
                </td>
            </tr>
        </tfoot>
    </table>

    @if ($quotation->remarks)
        <p>備考: {{ $quotation->remarks }}</p>
    @endif
@endsection
