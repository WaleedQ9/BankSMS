@extends('layouts.app')

@section('title', 'الرئيسية')
@section('page-title', 'متابعة المصاريف')
@section('page-subtitle', $cycle->is_open ? 'بدأت الدورة في ' . $cycle->start_date->format('j/n/Y') . ' — الراتب المتوقع '
    . $expectedSalaryDate->format('j/n/Y') : $cycle->start_date->format('j/n/Y') . ' - ' . $cycle->end_date->format('j/n/Y'))

@section('content')
    <!-- Floating Add Button -->
    <a href="{{ route('transactions.create') }}"
        style="position:fixed;bottom:calc(var(--nav-height) + 16px);right:16px;z-index:999;width:56px;height:56px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;box-shadow:0 4px 12px rgba(139,111,78,0.4);text-decoration:none;">+</a>

    @if ($pendingSalaryConfirmation)
        <div class="modal fade" id="salaryConfirmationModal" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content" style="border:0;border-radius:18px;overflow:hidden;">
                    <div class="modal-body p-4">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:16px;">
                            <span style="display:grid;place-items:center;width:42px;height:42px;border-radius:13px;background:var(--accent-bg);font-size:1.35rem;">💰</span>
                            <div>
                                <div style="font-weight:800;font-size:1rem;">رسالة راتب تحتاج تأكيدك</div>
                                <div style="font-size:.72rem;color:var(--text-muted);">لن تتغير الدورة إلا بعد اختيارك</div>
                            </div>
                        </div>
                        <div style="padding:12px;background:var(--bg-input);border-radius:12px;font-size:.82rem;display:grid;gap:8px;">
                            <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:var(--text-secondary);">المبلغ</span><strong>{{ number_format($pendingSalaryConfirmation->amount, 2) }} ريال</strong></div>
                            <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:var(--text-secondary);">التاريخ</span><strong>{{ $pendingSalaryConfirmation->transaction_date->format('j/n/Y H:i') }}</strong></div>
                            @if ($pendingSalaryConfirmation->merchant)
                                <div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:var(--text-secondary);">الوصف</span><strong>{{ $pendingSalaryConfirmation->merchant }}</strong></div>
                            @endif
                        </div>
                    </div>
                    <div class="modal-footer" style="border:0;padding:0 24px 24px;display:grid;gap:8px;">
                        <form method="POST" action="{{ route('salary-confirmations.resolve', $pendingSalaryConfirmation) }}">@csrf<button name="decision" value="start_cycle" class="btn btn-accent w-100">نعم، ابدأ دورة جديدة</button></form>
                        <form method="POST" action="{{ route('salary-confirmations.resolve', $pendingSalaryConfirmation) }}">@csrf<button name="decision" value="record_income" class="btn btn-outline w-100">لا، سجّلها دخلاً في الدورة الحالية</button></form>
                        <form method="POST" action="{{ route('salary-confirmations.resolve', $pendingSalaryConfirmation) }}">@csrf<button name="decision" value="ignore" class="btn btn-outline w-100" style="color:var(--danger);border-color:var(--danger);">تجاهل الرسالة</button></form>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Summary Cards -->
    <div class="row g-2 mb-4">
        <div class="col-12">
            <div class="summary-card" style="padding:12px 16px;">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-weight:700;font-size:0.95rem;">الفترة {{ $week->week_number }} من 4</span>
                    <span style="font-size:0.75rem;color:var(--text-muted);">{{ $week->start_date->format('j/n/Y') }} -
                        {{ $week->end_date->format('j/n/Y') }}</span>
                </div>
                <div class="budget-progress" style="height:8px;margin-bottom:6px;">
                    <div class="fill"
                        style="width:{{ round(($weekDaysPassed / $weekTotalDays) * 100) }}%;background:var(--accent);">
                    </div>
                </div>
                <div class="d-flex justify-content-between" style="font-size:0.75rem;color:var(--text-muted);">
                    <span>اليوم {{ $weekDaysPassed }} من {{ $weekTotalDays }}</span>
                    <span>باقي {{ $weekDaysLeft }} يوم</span>
                </div>
                <div style="margin-top:8px;padding-top:8px;border-top:1px dashed var(--border);font-size:.7rem;color:var(--text-muted);">
                    الراتب المتوقع {{ $expectedSalaryDate->format('j/n/Y') }} — متبقي {{ $cycleDaysLeft }} يوم
                </div>
            </div>
        </div>
        <div class="col-6">
            <div class="summary-card">
                <div class="label">مصروف الفترة</div>
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

    <!-- Current spending period -->
    @if (count($weeklyStats) > 0)
        <div class="section-title">خطة الصرف للفترة الحالية</div>
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
                        <div
                            style="font-size:0.7rem;font-weight:700;color:{{ $stat['allowance'] - $stat['spent'] >= 0 ? '#3A9A6C' : '#D94F4F' }};">
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
                            {{ number_format($stat['spent'], 0) }} / {{ number_format($stat['effective_budget'], 0) }}
                            ريال
                            @if (($stat['carried'] ?? 0) > 0)
                                <div style="color:var(--success);">الأساسي {{ number_format($stat['budget'], 0) }} + مرحّل
                                    {{ number_format($stat['carried'], 0) }}</div>
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

    @if ($totalOverages > 0)
        <div class="col-12">
            <div style="padding:14px 15px;background:#FFF7F5;border:1px solid #F3CCC6;border-radius:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span
                        style="display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:#FCE1DC;font-size:1.05rem;">⚠️</span>
                    <div style="flex:1;">
                        <div style="font-weight:800;font-size:.86rem;color:#A83C32;">تسوية التجاوزات المتوقعة</div>
                        <div style="font-size:.7rem;color:#9B625B;margin-top:2px;">{{ $overageCategoriesCount }}
                            {{ $overageCategoriesCount === 1 ? 'بند متجاوز' : 'بنود متجاوزة' }} خلال الدورة الحالية</div>
                    </div>
                    <div style="font-weight:800;font-size:1.05rem;color:#C04438;white-space:nowrap;">
                        {{ number_format($totalOverages, 0) }} ريال</div>
                </div>

                <div style="margin:11px 0 9px;border-top:1px dashed #ECC7C0;"></div>
                @foreach ($overageItems as $item)
                    <div
                        style="display:flex;justify-content:space-between;align-items:center;font-size:.74rem;padding:3px 0;color:#704841;">
                        <span>{{ $item['icon'] }} {{ $item['name'] }}</span>
                        <strong style="color:#C04438;">تجاوز {{ number_format($item['amount'], 0) }} ريال</strong>
                    </div>
                @endforeach

                @if ($autoSettleOverages && $overageSource && $overageSourceRemainingAfter !== null)
                    <div style="margin:10px 0 8px;border-top:1px dashed #ECC7C0;"></div>
                    <div
                        style="display:flex;justify-content:space-between;align-items:center;font-size:.74rem;color:#704841;">
                        <span>سيُغطى من {{ $overageSource->icon }} {{ $overageSource->name }} عند إغلاق الدورة</span>
                        <strong style="color:#A83C32;">{{ number_format($overageCoverage, 0) }} ريال</strong>
                    </div>
                    <div
                        style="margin-top:7px;padding:8px 10px;border-radius:9px;background:#FFF0ED;font-size:.72rem;color:#8B544C;">
                        المتبقي المتوقع في {{ $overageSource->name }} بعد التسوية:
                        <strong style="color:#A83C32;">{{ number_format($overageSourceRemainingAfter, 0) }} ريال</strong>
                        @if ($overageUncovered > 0)
                            <span style="display:block;margin-top:3px;color:#C04438;">يتبقى
                                {{ number_format($overageUncovered, 0) }} ريال غير مغطى.</span>
                        @endif
                    </div>
                @else
                    <div style="margin-top:10px;font-size:.7rem;color:#9B625B;">لن تُخصم التجاوزات تلقائياً إلا عند تفعيل
                        مصدر التسوية من الإعدادات.</div>
                @endif
            </div>
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

@if ($pendingSalaryConfirmation)
    @push('scripts')
        <script>
            new bootstrap.Modal(document.getElementById('salaryConfirmationModal')).show();
        </script>
    @endpush
@endif
