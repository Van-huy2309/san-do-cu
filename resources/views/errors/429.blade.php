<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tạm chậm lại — Relic</title>
    <link rel="stylesheet" href="{{ url('css/relic.css') }}">
</head>
<body class="acct-body">
<main class="panel" style="max-width:520px;margin:48px auto">
    <h1>Tạm chậm lại</h1>
    <p>{{ $message }}</p>
    <p class="muted">Hệ thống đã ghi nhận và báo quản trị. Một người dùng bình thường không chạm mức này.</p>
    <a class="btn btn-accent" href="{{ route('home') }}">Về chợ</a>
</main>
</body>
</html>
