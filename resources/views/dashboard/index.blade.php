@extends('layouts.app')

@section('title', 'الرئيسية')
@section('page-title', 'متابعة المصاريف')
@section('page-subtitle', $cycle->is_open ? 'بدأت الدورة في ' . $cycle->start_date->format('j/n/Y') . ' — حتى وصول الراتب القادم' : $cycle->start_date->format('j/n/Y') . ' - ' . $cycle->end_date->format('j/n/Y'))

@section('content')
    <!-- Floating Add Button -->
    <a href="{{ route('transactions.create') }}"
        style="position:fixed;bottom:calc(var(--nav-height) + 16px);right:16px;z-index:999;width:56px;height:56px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;box-shadow:0 4px 12px rgba(139,111,78,0.4);text-decoration:none;">+</a>

    <!-- Summary Cards -->
    <div class="row g-2 mb-4">
        <div class="col-12">
            <div class="summary-card" style="padding:12px 16px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-weight:700;font-size:0.95rem;">الأسبوع {{ $week->week_number }}</span>
                    <span style="font-size:0.75rem;color:var(--text-muted);">{{ $week->start_date->format('j/n/Y') }} - {{ $week->end_date->format('j/n/Y') }}</span>
                </div>
                <div class="budget-progress" style="height:8px;margin-bottom:6px;">
                    <div class="fill" style="width:{{ round(($weekDaysPassed / $weekTotalDays) * 100) }}%;background:var(--accent);"></div>
                </div>
                <div class="d-flex justify-content-between" style="font-size:0.75rem;color:var(--text-muted);">
                    <span>اليوم {{ $weekDaysPassed }} من {{ $weekTotalDays }}</span>
                    <span>باقي {{ $weekDaysLeft }} يوم</span>
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="summary-card">
                <div class="label">مصروف الأسبوع</div>
                <div class="amount">{{ number_format($weeklyTotal, 0) }}</div>
                <div class="label" style="color:var(--text-muted);font-size:0.7rem;">من
                    {{ number_format($weeklyAllowanceTotal, 0) }} ريال</div>
            </div>
        </div>
        <div class="col-6">
            <div class="summary-card">
                <div class="label">مصروف الشهر</div>
                <div class="amount">{{ number_format($monthlyTotal, 0) }}</div>
                <div class="label">ريال</div>
            </div>
        </div>
        <div class="col-6">
            <div class="summary-card">
                <div class="label">الوارد</div>
                <div class="amount" style="color:var(--success);">{{ number_format($incomeTotal, 0) }}</div>
                <div class="label">ريال</div>
            </div>
        </div>
        <div class="col-6">
            <div class="summary-card">
                <div class="label">عدد العمليات</div>
                <div class="amount">{{ $transactionCount }}</div>
                <div class="label">عملية</div>
            </div>
        </div>

    </div>

    <!-- Weekly Budget -->
    @if (count($weeklyStats) > 0)
        <div class="section-title">الميزانية الأسبوعية</div>
        <div class="row g-2 mb-3">
            @foreach ($weeklyStats as $stat)
                @php
                    $color = $stat['category']->color;
                    if ($stat['percentage'] >= 100) {
                        $color = '#EF4444';
                    } elseif ($stat['percentage'] >= 80) {
                        $color = '#F59E0B';
                    }
                @endphp
                <div class="col-6">
                    <div class="cat-box" style="border-top: 3px solid {{ $color }};">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="font-size:1.4rem;">{{ $stat['category']->icon }}</span>
                            <span
                                style="font-size:0.75rem;font-weight:700;color:{{ $color }};">{{ $stat['percentage'] }}%</span>
                        </div>
                        <div style="font-weight:700;font-size:0.85rem;margin-bottom:6px;">{{ $stat['category']->name }}
                        </div>
                        <div class="budget-progress" style="margin:4px 0;">
                            <div class="fill"
                                style="width: {{ min($stat['percentage'], 100) }}%; background: {{ $color }};">
                            </div>
                        </div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">
                            {{ number_format($stat['spent'], 0) }} / {{ number_format($stat['allowance'], 0) }} ريال
                        </div>
                        <div style="font-size:0.7rem;font-weight:700;color:{{ ($stat['allowance'] - $stat['spent']) >= 0 ? '#3A9A6C' : '#D94F4F' }};">
                            متبقي {{ number_format(max(0, $stat['allowance'] - $stat['spent']), 0) }} ريال
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <!-- Monthly Budget -->
    @if (count($monthlyStats) > 0)
        <div class="section-title">الميزانية الشهرية</div>
        <div class="row g-2 mb-3">
            @foreach ($monthlyStats as $stat)
                @php
                    $color = $stat['category']->color;
                    if ($stat['percentage'] >= 100) {
                        $color = '#EF4444';
                    } elseif ($stat['percentage'] >= 80) {
                        $color = '#F59E0B';
                    }
                @endphp
                <div class="col-6">
                    <div class="cat-box" style="border-top: 3px solid {{ $color }};">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span style="font-size:1.4rem;">{{ $stat['category']->icon }}</span>
                            <span
                                style="font-size:0.75rem;font-weight:700;color:{{ $color }};">{{ $stat['percentage'] }}%</span>
                        </div>
                        <div style="font-weight:700;font-size:0.85rem;margin-bottom:6px;">{{ $stat['category']->name }}
                        </div>
                        <div class="budget-progress" style="margin:4px 0;">
                            <div class="fill"
                                style="width: {{ min($stat['percentage'], 100) }}%; background: {{ $color }};">
                            </div>
                        </div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">
                            {{ number_format($stat['spent'], 0) }} / {{ number_format($stat['effective_budget'], 0) }} ريال
                            @if(($stat['carried'] ?? 0) > 0)
                                <div style="color:var(--success);">الأساسي {{ number_format($stat['budget'], 0) }} + مرحّل {{ number_format($stat['carried'], 0) }}</div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <div class="icon">📊</div>
            <div>لم تُحدد ميزانيات بعد</div>
            <a href="{{ route('categories.index') }}" class="btn btn-accent btn-sm mt-2">حدد الميزانية</a>
        </div>
    @endif

    <!-- Expense Calendar -->
    <div class="section-title mt-4">تقويم المصروفات — {{ $calendarMonth->format('m/Y') }}</div>
    <div class="card-main">
        <div class="dashboard-calendar-weekdays"><span>أحد</span><span>اثن</span><span>ثلا</span><span>أرب</span><span>خمي</span><span>جمع</span><span>سبت</span></div>
        <div class="dashboard-expenses-calendar">
            @foreach($calendarDays as $day)
                @if($day === null)
                    <div class="dashboard-calendar-day empty"></div>
                @else
                    <button type="button" class="dashboard-calendar-day {{ $day['total'] > 0 ? 'has-spending' : '' }}" onclick="showDashboardDay('{{ $day['date'] }}')">
                        <span>{{ $day['day'] }}</span>
                        @if($day['total'] > 0)
                            <strong>{{ number_format($day['total'], 0) }}</strong>
                            <small title="{{ $day['largest'] }}">{{ $day['largest'] }}</small>
                        @endif
                    </button>
                @endif
            @endforeach
        </div>
        <div id="dashboardDayTransactions" class="dashboard-day-transactions" style="display:none;"></div>
    </div>

    <!-- Recent Transactions -->
    <div class="section-title mt-4">آخر المعاملات</div>
    @forelse($recentTransactions as $tx)
        @php
            $icon = $tx->category ? $tx->category->icon : ($tx->type === 'income' ? '💰' : '📄');
            $isIncome = $tx->type === 'income';
        @endphp
        <div class="tx-card">
            <div class="tx-icon">{{ $icon }}</div>
            <div class="tx-info">
                <div class="tx-merchant">{{ $tx->merchant ?: $tx->type }}</div>
                <div class="tx-date">{{ $tx->transaction_date->format('j/n/Y H:i') }}</div>
            </div>
            <div class="tx-amount {{ $isIncome ? 'income' : 'expense' }}">
                {{ $isIncome ? '+' : '-' }}{{ number_format($tx->amount, 2) }}
            </div>
        </div>
    @empty
        <div class="empty-state">
            <div class="icon">📭</div>
            <div>لا توجد معاملات بعد</div>
        </div>
    @endforelse

    @if ($recentTransactions->count() >= 5)
        <div class="text-center mt-2">
            <a href="{{ route('transactions.index') }}" class="btn btn-outline">عرض الكل</a>
        </div>
    @endif
@endsection

@push('styles')
<style>
    .dashboard-calendar-weekdays,.dashboard-expenses-calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}
    .dashboard-calendar-weekdays{text-align:center;color:var(--text-muted);font-size:.65rem;margin-bottom:6px}
    .dashboard-calendar-day{min-height:70px;border:1px solid var(--border);border-radius:9px;padding:5px;background:#fff;text-align:right;overflow:hidden;color:var(--text-primary)}
    .dashboard-calendar-day.empty{border-color:transparent;background:transparent}.dashboard-calendar-day.has-spending{background:#FFF8E8;border-color:#F0D9A7;cursor:pointer}
    .dashboard-calendar-day span{display:block;font-size:.7rem;color:var(--text-muted)}.dashboard-calendar-day strong{display:block;font-size:.72rem;color:var(--danger);margin-top:3px}.dashboard-calendar-day small{display:block;font-size:.55rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
    .dashboard-day-transactions{margin-top:14px;padding-top:12px;border-top:1px solid var(--border)}.dashboard-day-transaction{display:flex;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--border);font-size:.8rem}
</style>
@endpush

@push('scripts')
<script>
    const dashboardCalendarTransactions = @json($calendarTransactionsData);
    function showDashboardDay(date) {
        const panel = document.getElementById('dashboardDayTransactions');
        const transactions = dashboardCalendarTransactions[date] || [];
        panel.style.display = 'block';
        const title = new Date(`${date}T12:00:00`).toLocaleDateString('ar-SA');
        if (!transactions.length) { panel.innerHTML = `<strong>عمليات ${title}</strong><div style="font-size:.8rem;color:var(--text-muted);margin-top:8px;">لا توجد مصروفات في هذا اليوم.</div>`; return; }
        panel.innerHTML = `<strong>عمليات ${title}</strong>` + transactions.map(t => `<div class="dashboard-day-transaction"><span>${escapeDashboardHtml(t.icon)} ${escapeDashboardHtml(t.merchant)}<small style="display:block;color:var(--text-muted);">${escapeDashboardHtml(t.time)}</small></span><strong style="color:var(--danger);">${escapeDashboardHtml(t.amount)} ريال</strong></div>`).join('');
    }
    function escapeDashboardHtml(value) { const element = document.createElement('div'); element.textContent = value; return element.innerHTML; }
</script>
@endpush
