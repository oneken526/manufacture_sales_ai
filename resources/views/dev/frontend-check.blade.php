<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <title>フロントエンド動作確認（jQuery + Bootstrap 5）</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="p-4">
    <div class="container">
        <h1 class="mb-3">jQuery + Bootstrap 5 動作確認</h1>

        <button id="show-alert" type="button" class="btn btn-primary">
            jQueryでBootstrapアラートを表示
        </button>

        <div id="alert-area" class="mt-3"></div>
    </div>

    <script>
        $(function () {
            $('#show-alert').on('click', function () {
                $('#alert-area').html(
                    '<div class="alert alert-success" role="alert">'
                    + 'jQueryのイベントハンドラからBootstrapのアラートコンポーネントを描画しました。'
                    + '</div>'
                );
            });
        });
    </script>
</body>
</html>
