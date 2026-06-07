{{-- 統合テスト用: 共通レイアウトを継承する簡易帳票テンプレート（PdfService統合フロー検証用フィクスチャ） --}}
@extends('pdf.layouts.base')

@section('content')
    <h1>{{ $title }}</h1>
    <p>発行日: {{ $issuedAt }}</p>
    <table>
        <tbody>
            <tr><td>件名</td><td>{{ $subject }}</td></tr>
        </tbody>
    </table>
@endsection
