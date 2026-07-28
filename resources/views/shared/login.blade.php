<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>صفحة مشتركة</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Tajawal', sans-serif; box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background: #F9F8F6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: #fff;
            border: 1px solid #E8E2DB;
            border-radius: 20px;
            padding: 40px 30px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
        }
        .icon { font-size: 3rem; margin-bottom: 16px; }
        .title { font-size: 1.3rem; font-weight: 800; color: #1a1a1a; margin-bottom: 8px; }
        .subtitle { font-size: 0.85rem; color: #7a7067; margin-bottom: 24px; }
        .pin-input {
            width: 100%;
            padding: 14px;
            font-size: 1.5rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 12px;
            border: 2px solid #E8E2DB;
            border-radius: 14px;
            background: #F3F1EE;
            color: #1a1a1a;
            outline: none;
            direction: ltr;
        }
        .pin-input:focus { border-color: #8B6F4E; background: #fff; box-shadow: 0 0 0 3px rgba(139,111,78,0.12); }
        .btn {
            width: 100%;
            padding: 14px;
            font-size: 1rem;
            font-weight: 700;
            background: #8B6F4E;
            color: #fff;
            border: none;
            border-radius: 14px;
            cursor: pointer;
            margin-top: 16px;
        }
        .btn:hover { background: #7A6143; }
        .alert { padding: 10px 16px; border-radius: 12px; font-size: 0.85rem; margin-bottom: 16px; }
        .alert-error { background: #FFEBEE; color: #C62828; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🔗</div>
        <div class="title">صفحة المصاريف</div>
        <div class="subtitle">أدخل الرمز السري للوصول</div>

        @if(session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif

        <form method="POST" action="{{ route('shared.verify') }}">
            @csrf
            <input type="password" name="pin" class="pin-input" maxlength="6" inputmode="numeric" pattern="[0-9]*" autofocus placeholder="----">
            <button type="submit" class="btn">دخول</button>
        </form>
    </div>
</body>
</html>
