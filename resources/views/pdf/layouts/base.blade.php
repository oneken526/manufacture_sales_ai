<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    @include('pdf.layouts.style')
</head>
<body>
    {{--
        【機能概要】: 全帳票（見積書・納品書・請求書）共通のヘッダー・フッター・会社情報レイアウト
        【実装方針】: mPDFの<htmlpageheader>/<htmlpagefooter>特殊タグを用いて、全ページに繰り返し表示される
                     ヘッダー・フッターを定義する。会社情報はハードコーディングせず config('company.*') から取得する。
                     各帳票テンプレートは @extends('pdf.layouts.base') し、@section('content') に内容を差し込む。
        【テスト対応】: TC5（共通レイアウトの会社情報がPDFに含まれることを検証する統合テスト）
        🟡 信頼性レベル: タスクファイル実装詳細2の指示（ヘッダー＝自社情報、フッター＝ページ番号・出力日時）に基づき、
                         具体的なHTML構成は一般的な帳票レイアウトから妥当に推測
    --}}
    <htmlpageheader name="pdf-common-header">
        <div class="pdf-header">
            <div class="company-name">{{ config('company.name') }}</div>
            <div class="company-info">
                〒{{ config('company.postal_code') }} {{ config('company.address') }} /
                TEL: {{ config('company.phone') }} / FAX: {{ config('company.fax') }} /
                登録番号: {{ config('company.registration_number') }}
            </div>
        </div>
    </htmlpageheader>

    <htmlpagefooter name="pdf-common-footer">
        <div class="pdf-footer">
            出力日時: {{ now()->format('Y-m-d H:i') }} ｜ ページ {PAGENO} / {nbpg}
        </div>
    </htmlpagefooter>

    <sethtmlpageheader name="pdf-common-header" value="on" show-this-page="1" />
    <sethtmlpagefooter name="pdf-common-footer" value="on" />

    @yield('content')
</body>
</html>
