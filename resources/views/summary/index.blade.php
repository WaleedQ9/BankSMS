@extends('layouts.app')

@section('title', 'ملخص الدورة')
@section('page-title', 'ملخص الدورة')
@section('page-subtitle', $cycle->start_date->format('d/m') . ' - ' . $cycle->end_date->format('d/m') . ($isArchived ? ' (مؤرشف)' : ''))

@section('content')
    @if(session('success'))
        <div class="alert-toast success">{{ session('success') }}</div>
    @endif

    <!-- Cycle Selector -->
    @if($cycles->count() > 1)
        <div class="card-main mb-3">
            <form method="GET" action="{{ route('summary.index') }}" class="d-flex align-items-center gap-2">
                <label class="form-label mb-0" style="white-space:nowrap;">الدورة:</label>
                <select name="cycle" class="form-select" onchange="this.form.submit()" style="font-size:0.85rem;">
                    @foreach($cycles as $c)
                        <option value="{{ $c->id }}" {{ $c->id == $cycle->id ? 'selected' : '' }}>
                            {{ $c->start_date->format('d/m/Y') }} - {{ $c->end_date->format('d/m/Y') }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    @endif

    <!-- Overview Cards -->
    <div class="row g-2 mb-3">
        <div class="col-3">
            <div class="summary-card">
                <div class="label">الدخل</div>
                <div class="amount" style="font-size:1rem;">{{ number_format($monthlyIncome, 0) }}</div>
            </div>
        </div>
        <div class="col-3">
            <div class="summary-card">
                <div class="label">غير مخصص</div>
                <div class="amount" style="font-size:1rem;color:{{ ($monthlyIncome - $totalBudget) >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ number_format($monthlyIncome - $totalBudget, 0) }}</div>
            </div>
        </div>
        <div class="col-3">
            <div class="summary-card">
                <div class="label">المصروفات</div>
                <div class="amount" style="font-size:1rem;color:var(--danger);">{{ number_format($totalSpent, 0) }}</div>
            </div>
        </div>
        <div class="col-3">
            <div class="summary-card">
                <div class="label">العمليات</div>
                <div class="amount" style="font-size:1rem;">{{ $transactionCount }}</div>
            </div>
        </div>
    </div>

    <!-- Transfer Card -->
    <div class="card-main mb-3" style="background: linear-gradient(135deg, #E8F5E9, #fff); text-align:center;">
        <div style="font-size:0.85rem;color:var(--text-secondary);">المبلغ المتبقي للتحويل للحساب الثاني</div>
        <div style="font-size:1.8rem;font-weight:800;color:var(--success);margin:8px 0;">{{ number_format($totalRemaining, 0) }} ريال</div>
        <div style="font-size:0.75rem;color:var(--text-muted);">إجمالي المتبقي من جميع البنود</div>
    </div>

    <!-- Per-Category Breakdown -->
    <div class="section-title">تفصيل البنود </div>
    @foreach($items as $item)
        <div class="card-main" style="border-right: 4px solid {{ $item['color'] }};">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <div>
                    <span style="font-size:1.2rem;">{{ $item['icon'] }}</span>
                    <span style="font-weight:700;font-size:0.9rem;">{{ $item['name'] }}</span>
                </div>
                <span style="font-size:0.75rem;color:var(--text-muted);">{{ $item['percentage'] }}%</span>
            </div>
            <div class="budget-progress">
                <div class="fill" style="width: {{ min($item['percentage'], 100) }}%; background: {{ $item['exceeded'] ? '#DC2626' : $item['color'] }};"></div>
            </div>
            <div class="d-flex justify-content-between" style="font-size:0.8rem;">
                <div>
                    <span style="color:var(--text-muted);">الميزانية:</span>
                    <span style="font-weight:700;">{{ number_format($item['budget'], 0) }}</span>
                    @if(($item['carried'] ?? 0) > 0)
                        <span style="color:var(--success);font-size:0.7rem;">+{{ number_format($item['carried'], 0) }}</span>
                    @endif
                </div>
                <div>
                    <span style="color:var(--text-muted);">المصروف:</span>
                    <span style="font-weight:700;color:var(--danger);">{{ number_format($item['spent'], 0) }}</span>
                </div>
                <div>
                    @if($item['exceeded'])
                        <span style="color:var(--danger);font-weight:700;">تجاوز {{ number_format(abs($item['remaining']), 0) }}</span>
                    @else
                        <span style="color:var(--success);font-weight:700;">متبقي {{ number_format($item['remaining'], 0) }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endforeach

    <!-- Other Spending -->
    @if($unbudgetedSpent > 0 || $unclassifiedSpent > 0)
        <div class="section-title mt-3">مصروفات أخرى</div>
        @if($unbudgetedSpent > 0)
            <div class="card-main">
                <div class="d-flex justify-content-between">
                    <span style="font-weight:700;font-size:0.85rem;">فئات بدون ميزانية</span>
                    <span style="font-weight:700;color:var(--danger);">{{ number_format($unbudgetedSpent, 0) }} ريال</span>
                </div>
            </div>
        @endif
        @if($unclassifiedSpent > 0)
            <div class="card-main">
                <div class="d-flex justify-content-between">
                    <span style="font-weight:700;font-size:0.85rem;">غير مصنفة</span>
                    <span style="font-weight:700;color:var(--warning);">{{ number_format($unclassifiedSpent, 0) }} ريال</span>
                </div>
            </div>
        @endif
    @endif

    <!-- Summary Footer -->
    <div class="card-main mt-3" style="background: linear-gradient(135deg, #f5f0eb, #fff);">
        <div class="d-flex justify-content-between mb-2" style="font-size:0.85rem;">
            <span style="color:var(--text-secondary);">إجمالي الميزانيات</span>
            <span style="font-weight:700;">{{ number_format($totalBudget, 0) }} ريال</span>
        </div>
        <div class="d-flex justify-content-between mb-2" style="font-size:0.85rem;">
            <span style="color:var(--text-secondary);">إجمالي المصروفات</span>
            <span style="font-weight:700;color:var(--danger);">{{ number_format($totalSpent, 0) }} ريال</span>
        </div>
        <div class="d-flex justify-content-between mb-2" style="font-size:0.85rem;">
            <span style="color:var(--text-secondary);">غير مخصص من الدخل</span>
            <span style="font-weight:700;color:{{ ($monthlyIncome - $totalBudget) >= 0 ? 'var(--success)' : 'var(--danger)' }};">{{ number_format($monthlyIncome - $totalBudget, 0) }} ريال</span>
        </div>
        <hr style="border-color:var(--border);margin:8px 0;">
        <div class="d-flex justify-content-between">
            <span style="font-weight:800;font-size:0.95rem;">المتبقي للتحويل</span>
            <span style="font-weight:800;font-size:1.1rem;color:var(--success);">{{ number_format($totalRemaining, 0) }} ريال</span>
        </div>
    </div>

    <!-- Pie Chart: Category Distribution -->
    @if($pieData->count() > 0)
        <div class="section-title mt-3">توزيع المصروفات</div>
        <div class="card-main">
            <canvas id="pieChart" height="250"></canvas>
        </div>
    @endif

    <!-- Bar Chart: Cycle Comparison -->
    @if($comparisonData->count() > 1)
        <div class="section-title mt-3">مقارنة الدورات</div>
        <div class="card-main">
            <canvas id="barChart" height="200"></canvas>
        </div>
    @endif

@endsection

@push('scripts')
@if(isset($pieData) && $pieData->count() > 0)
<script>
new Chart(document.getElementById('pieChart'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($pieData->pluck('name')) !!},
        datasets: [{
            data: {!! json_encode($pieData->pluck('spent')) !!},
            backgroundColor: {!! json_encode($pieData->pluck('color')) !!},
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { font: { family: 'Tajawal', size: 12 }, padding: 12 } }
        }
    }
});
</script>
@endif

@if(isset($comparisonData) && $comparisonData->count() > 1)
<script>
new Chart(document.getElementById('barChart'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($comparisonData->pluck('label')) !!},
        datasets: [{
            label: 'إجمالي المصروفات',
            data: {!! json_encode($comparisonData->pluck('spent')) !!},
            backgroundColor: ['#D4A574', '#8B6F4E', '#6B4E31'],
            borderRadius: 8,
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true, ticks: { font: { family: 'Tajawal' } } },
            x: { ticks: { font: { family: 'Tajawal', size: 11 } } }
        },
        plugins: {
            legend: { display: false }
        }
    }
});
</script>
@endif
@endpush
