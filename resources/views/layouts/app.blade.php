<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'متابعة المصاريف')</title>

    <!-- PWA -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#8B6F4E">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="مصاريفي">
    <link rel="apple-touch-icon" href="/img/wysms.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.rtl.min.css" rel="stylesheet">

    <style>
        :root {
            --bg-page: #F9F8F6;
            --bg-card: #FFFFFF;
            --bg-nav: #FFFFFF;
            --bg-input: #F3F1EE;
            --text-primary: #1a1a1a;
            --text-secondary: #7a7067;
            --text-muted: #a89e94;
            --border: #E8E2DB;
            --accent: #8B6F4E;
            --accent-light: #C9B59C;
            --accent-bg: #F3EDE4;
            --danger: #D94F4F;
            --success: #3A9A6C;
            --warning: #D4952B;
            --nav-height: 64px;
        }

        * { font-family: 'Tajawal', sans-serif; box-sizing: border-box; }

        body {
            background: var(--bg-page);
            color: var(--text-primary);
            padding-bottom: calc(var(--nav-height) + 16px);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
        }

        /* ── Header ── */
        .top-header {
            background: var(--bg-card);
            border-bottom: 1px solid var(--border);
            padding: 14px 20px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .top-header h1 { font-size: 1.15rem; font-weight: 800; margin: 0; color: var(--text-primary); }
        .top-header .subtitle { font-size: 0.75rem; color: var(--text-secondary); margin-top: 2px; }

        /* ── Cards ── */
        .card-main {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        .summary-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .summary-card .amount { font-size: 1.4rem; font-weight: 800; color: var(--text-primary); }
        .summary-card .label { font-size: 0.75rem; color: var(--text-secondary); }

        /* ── Progress Bar ── */
        .budget-progress {
            height: 8px;
            border-radius: 4px;
            background: var(--bg-input);
            overflow: hidden;
            margin: 8px 0;
        }
        .budget-progress .fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        /* ── Category Card ── */
        .category-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 10px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .category-card .cat-icon { font-size: 1.3rem; }
        .category-card .cat-name { font-weight: 700; font-size: 0.9rem; color: var(--text-primary); }
        .category-card .cat-detail { font-size: 0.75rem; color: var(--text-secondary); }
        .category-card .cat-percentage { font-size: 0.8rem; font-weight: 700; }

        /* ── Category Box (Dashboard Grid) ── */
        .cat-box {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            height: 100%;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }

        /* ── Transaction Card ── */
        .tx-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .tx-card .tx-icon { font-size: 1.5rem; min-width: 36px; text-align: center; }
        .tx-card .tx-info { flex: 1; min-width: 0; }
        .tx-card .tx-merchant {
            font-weight: 700; font-size: 0.9rem; color: var(--text-primary);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .tx-card .tx-date { font-size: 0.72rem; color: var(--text-muted); }
        .tx-card .tx-amount { font-weight: 800; font-size: 1rem; white-space: nowrap; }
        .tx-amount.income { color: var(--success); }
        .tx-amount.expense { color: var(--text-primary); }

        /* ── Filter Pills ── */
        .filter-pills {
            display: flex; gap: 8px; overflow-x: auto; padding: 8px 0;
            -webkit-overflow-scrolling: touch; scrollbar-width: none;
        }
        .filter-pills::-webkit-scrollbar { display: none; }
        .filter-pill {
            background: var(--bg-card); border: 1px solid var(--border); border-radius: 20px;
            padding: 7px 16px; font-size: 0.8rem; white-space: nowrap;
            color: var(--text-secondary); text-decoration: none; transition: all 0.2s;
        }
        .filter-pill.active, .filter-pill:hover {
            background: var(--accent); border-color: var(--accent); color: #fff;
        }

        /* ── Classify Grid ── */
        .classify-grid {
            display: grid; grid-template-columns: repeat(3, 1fr);
            gap: 6px; margin-top: 10px;
        }
        .classify-btn {
            background: var(--bg-input); border: 1px solid var(--border); border-radius: 10px;
            padding: 10px 4px; font-size: 0.75rem; color: var(--text-primary);
            text-align: center; cursor: pointer; transition: all 0.15s;
        }
        .classify-btn:hover { background: var(--accent-bg); border-color: var(--accent-light); }

        /* ── Bottom Navigation ── */
        .bottom-nav {
            position: fixed; bottom: 0; left: 0; right: 0;
            height: var(--nav-height); background: var(--bg-nav);
            border-top: 1px solid var(--border);
            display: flex; justify-content: space-around; align-items: center;
            z-index: 1000; padding-bottom: env(safe-area-inset-bottom, 0);
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
        }
        .nav-item {
            display: flex; flex-direction: column; align-items: center;
            text-decoration: none; color: var(--text-muted);
            font-size: 0.65rem; gap: 3px; padding: 6px 10px;
            position: relative; transition: color 0.2s;
        }
        .nav-item .nav-icon { font-size: 1.25rem; }
        .nav-item.active { color: var(--accent); }
        .nav-item .badge-count {
            position: absolute; top: 0; right: 0;
            background: var(--danger); color: #fff;
            font-size: 0.6rem; border-radius: 10px;
            padding: 1px 5px; font-weight: 700; min-width: 16px; text-align: center;
        }

        /* ── Content ── */
        .content { padding: 16px; max-width: 800px; margin: 0 auto; }

        .section-title {
            font-size: 0.85rem; font-weight: 700;
            color: var(--text-secondary); margin-bottom: 10px;
        }

        /* ── Buttons ── */
        .btn-accent {
            background: var(--accent); border: none; color: #fff;
            border-radius: 12px; padding: 10px 20px; font-weight: 600; font-size: 0.9rem;
        }
        .btn-accent:hover { background: #7A6143; color: #fff; }

        .btn-outline {
            background: transparent; border: 1px solid var(--border);
            color: var(--text-secondary); border-radius: 12px;
            padding: 8px 16px; font-size: 0.85rem; transition: all 0.2s;
        }
        .btn-outline:hover { background: var(--accent-bg); border-color: var(--accent-light); color: var(--text-primary); }

        /* ── Modals ── */
        .modal-content {
            background: var(--bg-card); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 16px;
        }
        .modal-header { border-bottom: 1px solid var(--border); }
        .modal-footer { border-top: 1px solid var(--border); }

        /* ── Form Controls ── */
        .form-control, .form-select {
            background: var(--bg-input); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 10px;
        }
        .form-control:focus, .form-select:focus {
            background: #fff; border-color: var(--accent);
            color: var(--text-primary);
            box-shadow: 0 0 0 3px rgba(139,111,78,0.12);
        }
        .form-label { font-size: 0.85rem; color: var(--text-secondary); font-weight: 600; }

        /* ── Empty State ── */
        .empty-state { text-align: center; padding: 50px 20px; color: var(--text-muted); }
        .empty-state .icon { font-size: 3rem; margin-bottom: 12px; }

        /* ── Alert ── */
        .alert-toast {
            border-radius: 12px; padding: 10px 16px; font-size: 0.85rem;
            margin-bottom: 16px; border: none;
        }
        .alert-toast.success { background: #E8F5E9; color: #2E7D32; }
        .alert-toast.error { background: #FFEBEE; color: #C62828; }

        /* ── Desktop Responsive ── */
        @media (min-width: 768px) {
            .content { padding: 24px 32px; }
            .top-header { padding: 16px 32px; }
            .bottom-nav {
                width: min(800px, 100vw);
                left: 50%;
                right: auto;
                transform: translateX(-50%);
                border-radius: 16px 16px 0 0;
            }
            .classify-grid { grid-template-columns: repeat(4, 1fr); }
        }

        .text-muted-custom { color: var(--text-muted) !important; }
        .fw-800 { font-weight: 800; }

        /* Alternate theme inspired by the shared financial page. */
        body.theme-shared {
            --bg-page: #f5f7f3;
            --bg-card: #fff;
            --bg-nav: rgba(255, 255, 255, .96);
            --bg-input: #f1f5f1;
            --text-primary: #18251f;
            --text-secondary: #527063;
            --text-muted: #718078;
            --border: #e1e8e3;
            --accent: #2f5d50;
            --accent-light: #82a99a;
            --accent-bg: #e8f1eb;
            --success: #23875f;
            --warning: #d38a31;
            background: linear-gradient(155deg, #e6f0e9 0, #f7f8f4 38%, #f5f3ef 100%);
        }
        body.theme-shared .top-header {
            position: relative;
            margin: 16px 16px 0;
            padding: 22px;
            border: 0;
            border-radius: 24px;
            color: #fff;
            overflow: hidden;
            background: linear-gradient(135deg, #224b40, #397464);
            box-shadow: 0 15px 35px rgba(33, 80, 67, .2);
        }
        body.theme-shared .top-header::before {
            content: '';
            position: absolute;
            width: 150px;
            height: 150px;
            top: -78px;
            left: -52px;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
        }
        body.theme-shared .top-header h1,
        body.theme-shared .top-header .subtitle { position: relative; z-index: 1; }
        body.theme-shared .top-header h1 { color: #fff; font-size: 1.3rem; }
        body.theme-shared .top-header .subtitle { color: #d8e6df; margin-top: 6px; }
        body.theme-shared .content { max-width: 760px; }
        body.theme-shared .card-main,
        body.theme-shared .summary-card,
        body.theme-shared .category-card,
        body.theme-shared .cat-box,
        body.theme-shared .tx-card {
            border-color: #e1e8e3;
            border-radius: 18px;
            box-shadow: 0 7px 17px rgba(44, 67, 54, .05);
        }
        body.theme-shared .summary-card { background: rgba(255, 255, 255, .92); }
        body.theme-shared .budget-progress { background: #e6ece7; border-radius: 99px; }
        body.theme-shared .btn-accent { background: #2f5d50; border-radius: 12px; }
        body.theme-shared .btn-accent:hover { background: #224b40; }
        body.theme-shared .btn-outline { border-color: #d5e1d8; color: #426657; }
        body.theme-shared .btn-outline:hover { background: #e8f1eb; border-color: #82a99a; }
        body.theme-shared .filter-pill.active,
        body.theme-shared .filter-pill:hover { background: #2f5d50; border-color: #2f5d50; }
        body.theme-shared .bottom-nav {
            border-color: #e1e8e3;
            box-shadow: 0 -5px 20px rgba(44, 67, 54, .08);
        }
        body.theme-shared .nav-item.active { color: #2f5d50; }
        @media (min-width: 768px) {
            body.theme-shared .top-header { max-width: 760px; margin: 20px auto 0; }
        }
        body.theme-shared .section-title {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            color: #365b4c;
            font-size: .88rem;
        }
        body.theme-shared .section-title::before {
            content: '';
            width: 4px;
            height: 18px;
            border-radius: 99px;
            background: linear-gradient(#4e8a74, #2f5d50);
        }
        body.theme-shared .dashboard-overview .period-card {
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #fbfffc, #eaf4ed);
            border-color: #d7e7dc;
        }
        body.theme-shared .dashboard-overview .period-card::after {
            content: '';
            position: absolute;
            width: 100px;
            height: 100px;
            left: -42px;
            bottom: -60px;
            border-radius: 50%;
            background: rgba(47, 93, 80, .06);
        }
        body.theme-shared .metric-card {
            position: relative;
            overflow: hidden;
            min-height: 108px;
            text-align: right;
        }
        body.theme-shared .metric-card .label:first-child { color: #527063; font-weight: 700; }
        body.theme-shared .metric-card .amount { margin: 7px 0 2px; color: #224b40; }
        body.theme-shared .cat-box {
            position: relative;
            overflow: hidden;
            padding: 15px;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        body.theme-shared .cat-box:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(44, 67, 54, .10); }
        body.theme-shared .cat-box::after {
            content: '';
            position: absolute;
            width: 48px;
            height: 48px;
            left: -25px;
            bottom: -26px;
            border-radius: 50%;
            background: rgba(47, 93, 80, .035);
        }
        body.theme-shared .tx-card {
            padding: 13px 15px;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        body.theme-shared .tx-card:hover { transform: translateY(-1px); box-shadow: 0 9px 20px rgba(44, 67, 54, .09); }
        body.theme-shared .tx-icon {
            display: grid;
            place-items: center;
            width: 40px;
            height: 40px;
            min-width: 40px;
            border-radius: 13px;
            background: #eef5f0;
            font-size: 1.2rem;
        }
        body.theme-shared .tx-amount.expense { color: #284d3e; }
        body.theme-shared .quick-add {
            background: linear-gradient(135deg, #397464, #224b40) !important;
            box-shadow: 0 10px 24px rgba(33, 80, 67, .32) !important;
            border: 3px solid #f5f7f3;
            transition: transform .18s ease;
        }
        body.theme-shared .quick-add:hover { color: #fff; transform: translateY(-2px) rotate(45deg); }
        body.theme-shared .filter-pills { gap: 7px; padding: 5px 0 10px; }
        body.theme-shared .filter-pill { background: rgba(255, 255, 255, .8); border-color: #dce8df; box-shadow: 0 3px 9px rgba(44, 67, 54, .035); }
        body.theme-shared .transactions-pager__list { border-color: #dce8df; box-shadow: 0 7px 17px rgba(44, 67, 54, .07); }
        body.theme-shared .transactions-pager__link:hover { background: #e8f1eb; color: #2f5d50; }
        body.theme-shared .transactions-pager__link.is-current { background: #2f5d50; box-shadow: 0 3px 8px rgba(47, 93, 80, .26); }
        body.theme-shared .bottom-nav {
            left: 10px;
            right: 10px;
            bottom: 10px;
            height: calc(var(--nav-height) - 2px);
            border-radius: 20px;
            border: 1px solid #e1e8e3;
        }
        body.theme-shared .bottom-nav .nav-item.active .nav-icon {
            display: grid;
            place-items: center;
            width: 30px;
            height: 26px;
            border-radius: 9px;
            background: #e7f1ea;
        }
        @media (min-width: 768px) {
            body.theme-shared .bottom-nav {
                width: min(760px, calc(100vw - 40px));
                left: 50%;
                right: auto;
                bottom: 12px;
                transform: translateX(-50%);
                border-radius: 20px;
            }
        }
    </style>
    @stack('styles')
</head>
@php
    $appTheme = \App\Models\Setting::getValue('app_theme', 'classic');
    $unclassifiedCount = \App\Models\Transaction::where('is_classified', false)
        ->whereIn('type', ['purchase', 'transfer', 'atm'])
        ->count();
@endphp
<body class="{{ $appTheme === 'shared' ? 'theme-shared' : '' }}">
    <div class="top-header">
        <h1>@yield('page-title', 'متابعة المصاريف')</h1>
        @hasSection('page-subtitle')
            <div class="subtitle">@yield('page-subtitle')</div>
        @endif
    </div>

    <div class="content">
        @yield('content')
    </div>

    <nav class="bottom-nav">
        <a href="{{ route('dashboard') }}" class="nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <span class="nav-icon">🏠</span>
            <span>الرئيسية</span>
        </a>
        <a href="{{ route('transactions.index') }}" class="nav-item {{ request()->routeIs('transactions.*') ? 'active' : '' }}">
            <span class="nav-icon">📋</span>
            <span>المعاملات</span>
        </a>
        <a href="{{ route('unclassified.index') }}" class="nav-item {{ request()->routeIs('unclassified.*') ? 'active' : '' }}">
            <span class="nav-icon">⚠️</span>
            <span>غير مصنف</span>
            @if($unclassifiedCount > 0)
                <span class="badge-count">{{ $unclassifiedCount }}</span>
            @endif
        </a>
        <a href="{{ route('summary.index') }}" class="nav-item {{ request()->routeIs('summary.*') ? 'active' : '' }}">
            <span class="nav-icon">📊</span>
            <span>الملخص</span>
        </a>
        <a href="{{ route('settings.index') }}" class="nav-item {{ request()->routeIs('settings.*') ? 'active' : '' }}">
            <span class="nav-icon">⚙️</span>
            <span>الإعدادات</span>
        </a>
    </nav>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js');
        }
    </script>
</body>
</html>
