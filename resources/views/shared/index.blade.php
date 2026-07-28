<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>الخطة المالية </title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest-shared.json">
    <meta name="theme-color" content="#8B6F4E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="مصاريفي">
    <link rel="apple-touch-icon" href="/img/icon.png">

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
            padding: 16px;
            padding-bottom: 32px;
        }

        .page-header {
            text-align: center;
            padding: 20px 0 16px;
        }

        .page-title {
            font-size: 1.3rem;
            font-weight: 800;
            color: #1a1a1a;
        }

        .page-subtitle {
            font-size: 0.75rem;
            color: #7a7067;
            margin-top: 4px;
        }

        .cat-card {
            background: #fff;
            border: 1px solid #E8E2DB;
            border-radius: 16px;
            padding: 16px;
            margin-bottom: 12px;
        }

        .cat-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }

        .cat-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
        }

        .cat-name {
            font-size: 1rem;
            font-weight: 700;
            color: #1a1a1a;
        }

        .budget-section {
            margin-bottom: 12px;
        }

        .budget-section:last-child {
            margin-bottom: 0;
        }

        .budget-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.75rem;
            color: #7a7067;
            margin-bottom: 6px;
        }

        .budget-label .amount {
            font-weight: 700;
            color: #1a1a1a;
        }

        .budget-label .remaining {
            font-weight: 700;
        }

        .remaining-positive {
            color: #3A9A6C;
        }

        .remaining-negative {
            color: #D94F4F;
        }

        .progress-bar {
            height: 8px;
            background: #F3F1EE;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .section-divider {
            border: none;
            border-top: 1px dashed #E8E2DB;
            margin: 10px 0;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7a7067;
        }

        .empty-state .icon {
            font-size: 3rem;
            margin-bottom: 12px;
        }
    </style>
</head>

<body>
    <div class="page-header">
        <div class="page-title">خطة مالية</div>
        @if ($cycle)
            <div class="page-subtitle">{{ $cycle->start_date->format('d/m') }} - {{ $cycle->end_date->format('d/m') }}
            </div>
        @endif
    </div>

    @if ($week)
        <div style="background:#fff;border:1px solid #E8E2DB;border-radius:16px;padding:12px 16px;margin-bottom:16px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;">
                <span style="font-weight:700;font-size:0.95rem;">الأسبوع {{ $week->week_number }}</span>
                <span style="font-size:0.75rem;color:#7a7067;">{{ $week->start_date->format('d/m') }} -
                    {{ $week->end_date->format('d/m') }}</span>
            </div>
            <div class="progress-bar" style="margin-bottom:6px;">
                <div class="progress-fill"
                    style="width:{{ round(($weekDaysPassed / $weekTotalDays) * 100) }}%;background:#8B6F4E;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;font-size:0.75rem;color:#7a7067;">
                <span>اليوم {{ $weekDaysPassed }} من {{ $weekTotalDays }}</span>
                <span>باقي {{ $weekDaysLeft }} يوم</span>
            </div>
        </div>
    @endif

    @if ($categories->isEmpty())
        <div class="empty-state">
            <div class="icon">📭</div>
            <div>لا توجد فئات مشتركة</div>
        </div>
    @else
        @foreach ($categories as $cat)
            <div class="cat-card">
                <div class="cat-header">
                    <div class="cat-icon" style="background: {{ $cat->color }}20;">{{ $cat->icon }}</div>
                    <div class="cat-name">{{ $cat->name }}</div>
                </div>

                {{-- Monthly Budget --}}
                @if ($cat->monthly_budget > 0)
                    <div class="budget-section">
                        <div class="budget-label">
                            <span>الشهري</span>
                            <span>
                                <span class="amount">{{ number_format($cat->monthly_spent, 0) }}</span>
                                <span> / {{ number_format($cat->monthly_budget, 0) }}</span>
                            </span>
                        </div>
                        <div class="budget-label">
                            <span>المتبقي</span>
                            <span
                                class="remaining {{ $cat->monthly_remaining >= 0 ? 'remaining-positive' : 'remaining-negative' }}">
                                {{ number_format(abs($cat->monthly_remaining), 0) }}
                                {{ $cat->monthly_remaining < 0 ? '-' : '' }}
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill"
                                style="width: {{ min($cat->monthly_percent, 100) }}%; background: {{ $cat->monthly_percent > 100 ? '#D94F4F' : ($cat->monthly_percent > 80 ? '#D4952B' : '#3A9A6C') }};">
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Weekly Budget --}}
                @if ($cat->has_weekly)
                    <hr class="section-divider">
                    <div class="budget-section">
                        <div class="budget-label">
                            <span>الأسبوعي</span>
                            <span>
                                <span class="amount">{{ number_format($cat->weekly_spent, 0) }}</span>
                                <span> / {{ number_format($cat->weekly_allowance, 0) }}</span>
                            </span>
                        </div>
                        <div class="budget-label">
                            <span>المتبقي</span>
                            <span
                                class="remaining {{ $cat->weekly_remaining >= 0 ? 'remaining-positive' : 'remaining-negative' }}">
                                {{ number_format(abs($cat->weekly_remaining), 0) }}
                                {{ $cat->weekly_remaining < 0 ? '-' : '' }}
                            </span>
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill"
                                style="width: {{ min($cat->weekly_percent, 100) }}%; background: {{ $cat->weekly_percent > 100 ? '#D94F4F' : ($cat->weekly_percent > 80 ? '#D4952B' : '#3A9A6C') }};">
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @endif

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
        setTimeout(() => location.reload(), 10000);
    </script>
</body>

</html>
