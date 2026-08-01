@extends('layouts.app')

@section('title', 'ملخص الدورة')
@section('page-title', 'ملخص الدورة')
@section('page-subtitle', $cycle->start_date->format('j/n/Y') . ' - ' . $cycle->end_date->format('j/n/Y') . ($isArchived ? ' (مؤرشف)' : ''))

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
                            {{ $c->start_date->format('j/n/Y') }} - {{ $c->end_date->format('j/n/Y') }}
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
        <div class="card-main" style="border-right: 4px solid {{ $item['color'] }};cursor:pointer;" onclick="showTransactions({{ $item['id'] ?? 0 }}, '{{ $item['icon'] }} {{ $item['name'] }}')">
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

    <!-- Transactions Modal -->
    <div id="txModal" onclick="if(event.target===this)closeModal()" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;z-index:1050;background:rgba(0,0,0,0.5);">
        <div style="position:fixed;bottom:0;left:0;right:0;max-height:75vh;background:#fff;border-radius:16px 16px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,0.15);display:flex;flex-direction:column;">
            <div style="padding:16px 20px 12px;border-bottom:1px solid var(--border);">
                <div style="width:40px;height:4px;background:var(--border);border-radius:2px;margin:0 auto 12px;"></div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span id="txModalTitle" style="font-weight:700;font-size:1.05rem;color:var(--text-primary);"></span>
                    <span onclick="closeModal()" style="cursor:pointer;font-size:1.3rem;color:var(--text-muted);padding:4px 8px;">✕</span>
                </div>
            </div>
            <div id="txModalBody" style="overflow-y:auto;padding:8px 20px 20px;flex:1;"></div>
        </div>
    </div>

    <script>
    function showTransactions(catId, title) {
        if (!catId) return;
        document.getElementById('txModalTitle').textContent = title;
        document.getElementById('txModalBody').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">جاري التحميل...</div>';
        document.getElementById('txModal').style.display = 'block';
        document.body.style.overflow = 'hidden';

        fetch('/summary/transactions/' + catId + '?cycle={{ $cycle->id }}')
            .then(r => r.json())
            .then(data => {
                if (!data.length) {
                    document.getElementById('txModalBody').innerHTML = '<div style="text-align:center;padding:30px;color:var(--text-muted);">لا توجد عمليات</div>';
                    return;
                }
                let html = '<div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:8px;">' + data.length + ' عملية</div>';
                data.forEach(function(tx) {
                    var d = tx.transaction_date ? new Date(tx.transaction_date) : null;
                    var date = d ? d.getDate() + '/' + (d.getMonth()+1) + '/' + d.getFullYear() : '';
                    html += '<div style="display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid #f0ece7;">';
                    html += '<div style="flex:1;min-width:0;">';
                    html += '<div style="font-weight:600;font-size:0.88rem;color:var(--text-primary);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + (tx.merchant || 'بدون تاجر') + '</div>';
                    html += '<div style="font-size:0.72rem;color:var(--text-muted);margin-top:2px;">' + date + '</div>';
                    html += '</div>';
                    html += '<div style="font-weight:700;color:var(--danger);font-size:0.9rem;margin-right:12px;white-space:nowrap;">' + Number(tx.amount).toLocaleString('ar-SA') + ' <span style="font-size:0.7rem;">ريال</span></div>';
                    html += '</div>';
                });
                document.getElementById('txModalBody').innerHTML = html;
            });
    }
    function closeModal() {
        document.getElementById('txModal').style.display = 'none';
        document.body.style.overflow = '';
    }
    </script>
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
