<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: meiryo, sans-serif; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>発行日: {{ $issuedAt }}</p>
    <p>{{ $message ?? '' }}</p>
</body>
</html>
