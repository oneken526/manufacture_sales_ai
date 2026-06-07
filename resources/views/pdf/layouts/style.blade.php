{{--
    【機能概要】: 帳票PDF共通のスタイル定義（罫線・フォントサイズ・余白等）
    【実装方針】: mPDFが解釈可能なインラインCSSとして定義し、共通レイアウトから@includeする
    【テスト対応】: TC5（共通レイアウト適用の統合テスト）でレイアウトが正しく描画されることを支える
    🟡 信頼性レベル: タスクファイル実装詳細2「帳票共通のスタイル（罫線・フォントサイズ・余白等）」の指示から
                     一般的な帳票デザインに基づき構成
--}}
<style>
    body {
        font-family: meiryo, sans-serif;
        font-size: 10pt;
        color: #222;
    }

    .pdf-header {
        width: 100%;
        border-bottom: 1px solid #333;
        padding-bottom: 6px;
        margin-bottom: 12px;
    }

    .pdf-header .company-name {
        font-size: 12pt;
        font-weight: bold;
    }

    .pdf-header .company-info {
        font-size: 8pt;
        color: #555;
    }

    .pdf-footer {
        width: 100%;
        border-top: 1px solid #333;
        padding-top: 4px;
        font-size: 8pt;
        color: #555;
        text-align: center;
    }

    table {
        border-collapse: collapse;
        width: 100%;
        margin-top: 8px;
    }

    table th,
    table td {
        border: 1px solid #333;
        padding: 4px 8px;
        font-size: 9pt;
    }

    table th {
        background-color: #f0f0f0;
    }
</style>
