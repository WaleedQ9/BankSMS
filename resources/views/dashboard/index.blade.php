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
                            {{ number_format($stat['spent'], 0) }} / {{ number_format($stat['budget'], 0) }} ريال
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
