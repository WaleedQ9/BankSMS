<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>الخطة المالية</title>
    <link rel="manifest" href="/manifest-shared.json">
    <meta name="theme-color" content="#2F5D50">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="الخطة المالية">
    <link rel="apple-touch-icon" href="/img/wysms.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #18251f;
            --muted: #6f7d75;
            --line: #e8eee9;
            --surface: #fff;
            --forest: #2f5d50;
            --cream: #f6f7f3;
            --good: #23875f;
            --warn: #d38a31;
            --danger: #d04d4d;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Tajawal', sans-serif;
        }

        body {
            min-height: 100vh;
            background: linear-gradient(155deg, #e6f0e9 0, #f7f8f4 38%, #f5f3ef 100%);
            color: var(--ink);
            padding: 18px 14px 36px;
        }

        .shell {
            max-width: 620px;
            margin: auto;
        }

        .hero {
            position: relative;
            overflow: hidden;
            color: #fff;
            padding: 24px 22px 21px;
            border-radius: 24px;
            background: linear-gradient(135deg, #224b40, #397464);
            box-shadow: 0 15px 35px rgba(33, 80, 67, .2);
        }

        .hero::before,
        .hero::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, .08);
        }

        .hero::before {
            width: 160px;
            height: 160px;
            left: -65px;
            top: -78px;
        }

        .hero::after {
            width: 100px;
            height: 100px;
            left: 35px;
            bottom: -62px;
        }

        .hero-top,
        .hero-copy {
            position: relative;
            z-index: 1;
        }

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: .8rem;
            font-weight: 700;
            color: #dcece4;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 35px;
            height: 35px;
            border-radius: 11px;
            background: rgba(255, 255, 255, .15);
            font-size: 1.05rem;
        }

        .live-dot {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 99px;
            font-size: .68rem;
            font-weight: 700;
            background: rgba(255, 255, 255, .12);
        }

        .live-dot i {
            width: 6px;
            height: 6px;
            background: #9ff0c7;
            border-radius: 50%;
            box-shadow: 0 0 0 3px rgba(159, 240, 199, .17);
        }

        .hero h1 {
            margin-top: 22px;
            font-size: 1.55rem;
            font-weight: 800;
            letter-spacing: -.4px;
        }

        .hero p {
            margin-top: 6px;
            font-size: .78rem;
            color: #d8e6df;
        }

        .week-card {
            margin: 14px 2px 20px;
            padding: 15px 16px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, .92);
            border-radius: 18px;
            box-shadow: 0 7px 17px rgba(44, 67, 54, .05);
        }

        .week-title,
        .week-meta,
        .budget-head,
        .budget-foot,
        .category-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .week-title strong {
            font-size: .9rem;
        }

        .week-title span {
            color: var(--muted);
            font-size: .7rem;
            direction: ltr;
        }

        .track {
            overflow: hidden;
            height: 7px;
            border-radius: 99px;
            background: #e6ece7;
        }

        .track>i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #8fb9a6, #2f5d50);
        }

        .week-card .track {
            margin: 12px 0 8px;
        }

        .week-meta {
            color: var(--muted);
            font-size: .72rem;
        }

        .overage-card {
            margin: 0 2px 20px;
            padding: 16px;
            border: 1px solid #f1d8d8;
            border-radius: 18px;
            background: linear-gradient(135deg, #fffafa, #fff4f2);
            box-shadow: 0 7px 17px rgba(126, 55, 55, .06);
        }

        .overage-head,
        .overage-line,
        .overage-source {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .overage-head strong {
            font-size: .9rem;
        }

        .overage-summary {
            display: flex;
            flex: 1;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            min-width: 0;
            padding: 0;
            border: 0;
            background: transparent;
            color: inherit;
            text-align: right;
            cursor: pointer;
        }

        .overage-total {
            color: var(--danger);
            font-size: 1.02rem;
            font-weight: 800;
        }

        .overage-list {
            display: grid;
            gap: 8px;
            margin-top: 13px;
        }

        .overage-details {
            display: none;
        }

        .overage-card.is-expanded .overage-details {
            display: block;
        }

        .overage-hint {
            margin-top: 7px;
            color: var(--muted);
            font-size: .64rem;
        }

        .overage-line {
            padding: 8px 10px;
            border-radius: 11px;
            background: rgba(255, 255, 255, .72);
            color: #5e4747;
            font-size: .74rem;
        }

        .overage-line b {
            color: var(--danger);
            white-space: nowrap;
        }

        .overage-source {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px dashed #ebcccc;
            color: #6b5555;
            font-size: .72rem;
        }

        .overage-source b {
            color: var(--forest);
            font-size: .8rem;
        }

        .overage-note {
            margin-top: 9px;
            color: var(--muted);
            font-size: .65rem;
            line-height: 1.6;
        }

        .section-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0 4px 10px;
        }

        .section-heading h2 {
            font-size: 1rem;
        }

        .section-heading span {
            color: var(--muted);
            font-size: .7rem;
        }

        .category-grid {
            display: grid;
            gap: 12px;
        }

        .category-card {
            --cat: #3a9a6c;
            position: relative;
            overflow: hidden;
            padding: 15px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 19px;
            box-shadow: 0 6px 16px rgba(41, 61, 47, .045);
        }

        .category-card.clickable {
            cursor: pointer;
            -webkit-tap-highlight-color: transparent;
        }

        .category-card.clickable:active {
            transform: scale(.988);
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 5px;
            height: 100%;
            background: var(--cat);
            opacity: .8;
        }

        .category-head {
            margin-bottom: 15px;
        }

        .category-name {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: .96rem;
        }

        .category-icon {
            display: grid;
            place-items: center;
            width: 38px;
            height: 38px;
            border-radius: 13px;
            font-size: 1.16rem;
            background: color-mix(in srgb, var(--cat) 15%, white);
        }

        .open-hint {
            color: var(--muted);
            font-size: .67rem;
        }

        .open-hint::after {
            content: '‹';
            font-size: 1.25rem;
            vertical-align: -2px;
            margin-right: 4px;
        }

        .budget-block+.budget-block {
            border-top: 1px dashed #e1e8e3;
            margin-top: 13px;
            padding-top: 13px;
        }

        .budget-head {
            color: var(--muted);
            font-size: .72rem;
            margin-bottom: 7px;
        }

        .budget-head strong {
            color: var(--ink);
            font-size: .82rem;
        }

        .percentage {
            padding: 4px 8px;
            border-radius: 9px;
            font-weight: 800;
            font-size: .67rem;
            color: var(--cat);
            background: color-mix(in srgb, var(--cat) 12%, white);
        }

        .budget-foot {
            margin-top: 7px;
            font-size: .7rem;
            color: var(--muted);
        }

        .remaining {
            font-weight: 800;
            font-size: .78rem;
        }

        .remaining.good {
            color: var(--good);
        }

        .remaining.bad {
            color: var(--danger);
        }

        .carry {
            margin-top: 6px;
            padding: 5px 8px;
            width: max-content;
            max-width: 100%;
            border-radius: 8px;
            color: #617067;
            background: #f1f5f1;
            font-size: .67rem;
        }

        .transactions {
            display: none;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px dashed #dfe7e0;
        }

        .transactions.open {
            display: block;
        }

        .transactions-title {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: .7rem;
            color: var(--muted);
        }

        .transaction {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
            padding: 9px 2px;
            border-bottom: 1px solid #f0f3f0;
            font-size: .75rem;
        }

        .transaction:last-child {
            border: 0;
        }

        .transaction small {
            display: block;
            color: var(--muted);
            font-size: .64rem;
            margin-top: 2px;
        }

        .transaction strong {
            white-space: nowrap;
            color: #283a30;
        }

        .empty {
            padding: 56px 18px;
            text-align: center;
            background: #fff;
            border: 1px dashed #d8e2da;
            border-radius: 20px;
            color: var(--muted);
        }

        .empty b {
            display: block;
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .footer {
            text-align: center;
            padding: 20px 0 0;
            color: #859088;
            font-size: .65rem;
        }

        @media (max-width:360px) {
            body {
                padding: 12px 10px 28px;
            }

            .hero {
                padding: 21px 17px;
            }

            .week-title {
                align-items: flex-start;
                flex-direction: column;
                gap: 4px;
            }
        }
    </style>
</head>

<body>
    <main class="shell">
        <header class="hero">
            <div class="hero-top">
                <div class="brand"><span class="brand-mark">◈</span> الخطة المالية</div><span class="live-dot"><i></i>
                    متابعة مباشرة</span>
            </div>
            <div class="hero-copy">
                <h1>نظرة على المصاريف</h1>
                <p>
                    @if ($cycle)
                        {{ $cycle->is_open ? 'الدورة بدأت في ' . $cycle->start_date->format('j/n/Y') . ' — الراتب المتوقع ' . $expectedSalaryDate->format('j/n/Y') : $cycle->start_date->format('j/n/Y') . ' — ' . $cycle->end_date->format('j/n/Y') }}
                    @else
                        لا توجد دورة مالية مفتوحة
                    @endif
                </p>
            </div>
        </header>

        @if ($week)
            <section class="week-card">
                <div class="week-title"><strong>الفترة
                        {{ $week->week_number }} من 4</strong><span>{{ $week->start_date->format('j/n/Y') }} —
                        {{ $week->end_date->format('j/n/Y') }}</span></div>
                <div class="track"><i
                        style="width:{{ min(100, round(($weekDaysPassed / max(1, $weekTotalDays)) * 100)) }}%"></i>
                </div>
                <div class="week-meta"><span>اليوم {{ $weekDaysPassed }} من {{ $weekTotalDays }}</span><span>متبقي
                        {{ $weekDaysLeft }} يوم</span></div>
                <div style="margin-top:9px;padding-top:9px;border-top:1px dashed #e1e8e3;color:#6f7d75;font-size:.7rem;">الراتب المتوقع
                    {{ $expectedSalaryDate->format('j/n/Y') }} — متبقي {{ $salaryDaysLeft }} يوم</div>
            </section>
        @endif

        @if ($totalOverages > 0)
            <section id="sharedOverageCard" class="overage-card">
                <div class="overage-head">
                    <button type="button" class="overage-summary" onclick="toggleOverageDetails()"
                        aria-expanded="false">
                        <strong>⚖️ التجاوزات للبنود</strong>
                        <span class="overage-total">{{ number_format($totalOverages, 0) }} ريال</span>
                    </button>
                </div>

                <p class="overage-hint">اضغط لعرض التفاصيل</p>
                <div class="overage-details">
                    <div class="overage-list">
                        @foreach ($overageItems as $item)
                            <div class="overage-line">
                                <span>{{ $item->icon }} {{ $item->name }}</span>
                                <b>تجاوز {{ number_format($item->amount, 0) }} ريال</b>
                            </div>
                        @endforeach
                    </div>

                    @if ($autoSettleOverages && $overageSource)
                        <div class="overage-source">
                            <span>التغطية المتوقعة من {{ $overageSource->icon }} {{ $overageSource->name }}</span>
                            <b>{{ number_format($overageCoverage, 0) }} ريال</b>
                        </div>
                        <div class="overage-source">
                            <span>المتبقي المتوقع في {{ $overageSource->name }} بعد التسوية</span>
                            <b>{{ number_format($overageSourceRemainingAfter, 0) }} ريال</b>
                        </div>
                        @if ($overageUncovered > 0)
                            <p class="overage-note">يتبقى {{ number_format($overageUncovered, 0) }} ريال غير مغطى؛ لأن
                                رصيد بند المصدر لا يكفي.</p>
                        @endif
                        <p class="overage-note">تُطبّق التسوية فعلياً عند إغلاق الدورة.</p>
                    @else
                        <p class="overage-note">لم تُفعّل تسوية التجاوزات التلقائية من الإعدادات.</p>
                    @endif
                </div>
            </section>
        @endif

        <div class="section-heading">
            <h2>البنود المشتركة</h2><span>{{ $categories->count() }} بنود</span>
        </div>
        @if ($categories->isEmpty())
            <div class="empty"><b>📭</b>لا توجد بنود مشتركة حالياً</div>
        @else
            <section class="category-grid">
                @foreach ($categories as $cat)
                    @php($barColor = $cat->monthly_percent >= 100 ? '#d04d4d' : ($cat->monthly_percent >= 80 ? '#d38a31' : $cat->color))
                    <article class="category-card {{ $transactionsLimit > 0 ? 'clickable' : '' }}"
                        style="--cat:{{ $barColor }}"
                        @if ($transactionsLimit > 0) onclick="toggleTransactions({{ $cat->id }})" @endif>
                        <div class="category-head">
                            <div class="category-name"><span
                                    class="category-icon">{{ $cat->icon }}</span><span>{{ $cat->name }}</span>
                            </div>
                            @if ($transactionsLimit > 0)
                                <span class="open-hint">العمليات</span>
                            @endif
                        </div>
                        @if ($cat->has_monthly)
                            <div class="budget-block">
                                <div class="budget-head"><span>إجمالي الدورة <span
                                            class="percentage">{{ $cat->monthly_percent }}%</span></span><strong>{{ number_format($cat->monthly_spent, 0) }}
                                        / {{ number_format($cat->monthly_budget, 0) }} ريال</strong></div>
                                <div class="track"><i
                                        style="width:{{ min($cat->monthly_percent, 100) }}%;background:{{ $barColor }}"></i>
                                </div>
                                @if ($cat->carried_balance > 0)
                                    <div class="carry">الأساسي {{ number_format($cat->base_budget, 0) }} + مُرحّل
                                        {{ number_format($cat->carried_balance, 0) }} ريال</div>
                                @endif
                                <div class="budget-foot"><span>المتبقي</span><span
                                        class="remaining {{ $cat->monthly_remaining >= 0 ? 'good' : 'bad' }}">{{ $cat->monthly_remaining >= 0 ? number_format($cat->monthly_remaining, 0) . ' ريال' : 'تجاوز ' . number_format(abs($cat->monthly_remaining), 0) . ' ريال' }}</span>
                                </div>
                            </div>
                        @endif
                        @if ($cat->has_weekly)
                            <div class="budget-block">
                                <div class="budget-head"><span>حصة الفترة <span class="percentage"
                                            style="color:{{ $cat->weekly_percent >= 100 ? '#d04d4d' : ($cat->weekly_percent >= 80 ? '#d38a31' : $cat->color) }}">{{ $cat->weekly_percent }}%</span></span><strong>{{ number_format($cat->weekly_spent, 0) }}
                                        / {{ number_format($cat->weekly_allowance, 0) }} ريال</strong></div>
                                <div class="track"><i
                                        style="width:{{ min($cat->weekly_percent, 100) }}%;background:{{ $cat->weekly_percent >= 100 ? '#d04d4d' : ($cat->weekly_percent >= 80 ? '#d38a31' : $cat->color) }}"></i>
                                </div>
                                <div class="budget-foot"><span>المتبقي للفترة</span><span
                                        class="remaining {{ $cat->weekly_remaining >= 0 ? 'good' : 'bad' }}">{{ $cat->weekly_remaining >= 0 ? number_format($cat->weekly_remaining, 0) . ' ريال' : 'تجاوز ' . number_format(abs($cat->weekly_remaining), 0) . ' ريال' }}</span>
                                </div>
                            </div>
                        @endif
                        @if ($transactionsLimit > 0)
                            <div id="transactions-{{ $cat->id }}" class="transactions">
                                <div class="transactions-title"><span>آخر {{ $transactionsLimit }}
                                        عمليات</span><span>اضغط للإغلاق</span></div>
                                <div class="transaction-list"><span style="font-size:.72rem;color:#6f7d75">اضغط لعرض
                                        العمليات</span></div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </section>
        @endif
        <div class="footer">❤️WNW</div>
    </main>

    <script>
        const sharedTransactionsBaseUrl = @json(url('/shared/transactions'));
        async function toggleTransactions(categoryId) {
            const panel = document.getElementById(`transactions-${categoryId}`);
            if (!panel) return;
            if (panel.classList.contains('open')) {
                panel.classList.remove('open');
                return;
            }
            panel.classList.add('open');
            if (panel.dataset.loaded) return;
            const list = panel.querySelector('.transaction-list');
            list.textContent = 'جارٍ تحميل العمليات…';
            try {
                const response = await fetch(`${sharedTransactionsBaseUrl}/${categoryId}`, {
                    credentials: 'same-origin'
                });
                if (!response.ok) throw new Error();
                const {
                    transactions
                } = await response.json();
                panel.dataset.loaded = '1';
                list.innerHTML = transactions.length ? transactions.map(transaction =>
                        `<div class="transaction"><div><strong>${escapeHtml(transaction.merchant)}</strong><small>${escapeHtml(transaction.date || '')}</small></div><strong>${escapeHtml(transaction.amount)} ريال</strong></div>`
                    ).join('') :
                    '<span style="font-size:.72rem;color:#6f7d75">لا توجد عمليات في هذا البند خلال الدورة الحالية.</span>';
            } catch {
                list.innerHTML = '<span style="font-size:.72rem;color:#d04d4d">تعذّر تحميل العمليات.</span>';
            }
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        const sharedOverageCard = document.getElementById('sharedOverageCard');

        function toggleOverageDetails() {
            if (!sharedOverageCard) return;
            const expanded = sharedOverageCard.classList.toggle('is-expanded');
            sharedOverageCard.querySelector('.overage-summary').setAttribute('aria-expanded', expanded ? 'true' : 'false');
            sharedOverageCard.querySelector('.overage-hint').textContent = expanded ? 'اضغط للإخفاء' : 'اضغط لعرض التفاصيل';
        }

        if ('serviceWorker' in navigator) navigator.serviceWorker.register('/sw.js');
    </script>
</body>

</html>
