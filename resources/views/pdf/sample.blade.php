<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: meiryo, sans-serif; }
        h1 { font-size: 18pt; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #333; padding: 6px 10px; }
    </style>
</head>
<body>
    <h1>動作確認用サンプル帳票</h1>
    <p>これはmPDFによる日本語PDF生成の動作確認用テンプレートです。</p>
    <table>
        <thead>
            <tr><th>項目</th><th>内容</th></tr>
        </thead>
        <tbody>
            <tr><td>発行日</td><td>{{ $issuedAt }}</td></tr>
            <tr><td>宛先</td><td>株式会社サンプル製作所 御中</td></tr>
            <tr><td>件名</td><td>製造業向け販売管理システム 環境構築 動作確認</td></tr>
        </tbody>
    </table>
</body>
</html>
