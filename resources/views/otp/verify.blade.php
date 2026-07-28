<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>تسجيل الدخول</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Tajawal', sans-serif;
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background: #F9F8F6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .otp-card {
            background: #fff;
            border: 1px solid #E8E2DB;
            border-radius: 20px;
            padding: 40px 30px;
            max-width: 380px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
        }

        .otp-icon {
            font-size: 3rem;
            margin-bottom: 16px;
        }

        .otp-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1a1a1a;
            margin-bottom: 8px;
        }

        .otp-subtitle {
            font-size: 0.85rem;
            color: #7a7067;
            margin-bottom: 24px;
        }

        .otp-input {
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

        .otp-input:focus {
            border-color: #8B6F4E;
            background: #fff;
            box-shadow: 0 0 0 3px rgba(139, 111, 78, 0.12);
        }

        .otp-btn {
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

        .otp-btn:hover {
            background: #7A6143;
        }

        .resend-btn {
            background: none;
            border: none;
            color: #8B6F4E;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 16px;
            text-decoration: underline;
        }

        .alert {
            padding: 10px 16px;
            border-radius: 12px;
            font-size: 0.85rem;
            margin-bottom: 16px;
        }

        .alert-error {
            background: #FFEBEE;
            color: #C62828;
        }

        .alert-success {
            background: #E8F5E9;
            color: #2E7D32;
        }
    </style>
</head>

<body>
    <div class="otp-card">
        <div class="otp-icon">🔐</div>
        <div class="otp-title">تسجيل الدخول</div>

        @if (session('error'))
            <div class="alert alert-error">{{ session('error') }}</div>
        @endif
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($otpSent)
            <div class="otp-subtitle">تم إرسال رمز التحقق</div>
            <div id="timer" style="font-size:0.85rem;font-weight:700;color:#D94F4F;margin-bottom:12px;"></div>

            <form method="POST" action="{{ route('otp.verify') }}">
                @csrf
                <input type="text" name="code" class="otp-input" maxlength="6" inputmode="numeric"
                    pattern="[0-9]*" autofocus autocomplete="one-time-code" placeholder="------">
                <button type="submit" class="otp-btn">تحقق</button>
            </form>

            <form method="POST" action="{{ route('otp.resend') }}">
                @csrf
                <button type="submit" class="resend-btn">إعادة إرسال الرمز</button>
            </form>
        @else
            <div class="otp-subtitle">سيتم إرسال رمز التحقق </div>

            <form method="POST" action="{{ route('otp.resend') }}">
                @csrf
                <button type="submit" class="otp-btn">إرسال رمز الدخول</button>
            </form>
        @endif
    </div>

    @if ($otpSent)
        <script>
            let seconds = 30;
            const timer = document.getElementById('timer');
            const interval = setInterval(() => {
                if (seconds <= 0) {
                    timer.textContent = 'انتهت صلاحية الرمز';
                    clearInterval(interval);
                    return;
                }
                timer.textContent = 'ينتهي خلال ' + seconds + ' ثانية';
                seconds--;
            }, 1000);
            timer.textContent = 'ينتهي خلال 30 ثانية';
        </script>
    @endif
</body>

</html>
