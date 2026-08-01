@extends('layouts.app')

@section('title', 'التقارير')
@section('page-title', 'التقارير')

@section('content')
    <ul class="nav nav-pills mb-3 gap-2" role="tablist" style="flex-wrap:nowrap;overflow-x:auto;">
        <li class="nav-item"><button class="filter-pill active" data-bs-toggle="pill" data-bs-target="#weeklyTab">أسبوعي</button></li>
        <li class="nav-item"><button class="filter-pill" data-bs-toggle="pill" data-bs-target="#monthlyTab">شهري</button></li>
        <li class="nav-item"><button class="filter-pill" data-bs-toggle="pill" data-bs-target="#compareTab">مقارنة</button></li>
    </ul>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="weeklyTab">
            <div class="card-main">
                <div class="section-title">مصاريف الأسابيع - {{ $cycle->start_date->format('j/n/Y') }}</div>
                <canvas id="weeklyChart" height="200"></canvas>
            </div>
        </div>
        <div class="tab-pane fade" id="monthlyTab">
            <div class="card-main">
                <div class="section-title">توزيع المصاريف على الفئات</div>
                <canvas id="monthlyChart" height="250"></canvas>
            </div>
        </div>
        <div class="tab-pane fade" id="compareTab">
            <div class="card-main">
                <div class="section-title">مقارنة آخر 3 أشهر</div>
                <canvas id="compareChart" height="200"></canvas>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    Chart.defaults.color = '#7a7067';
    Chart.defaults.borderColor = '#E8E2DB';
    Chart.defaults.font.family = 'Tajawal';

    new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: {!! json_encode(collect($weeklyData)->pluck('label')) !!},
            datasets: [{
                label: 'المصروف',
                data: {!! json_encode(collect($weeklyData)->pluck('total')) !!},
                backgroundColor: '#8B6F4E',
                borderRadius: 8,
                barThickness: 40,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() + ' ر.س' } } }
        }
    });

    const monthlyData = {!! json_encode($categoryData) !!};
    if (monthlyData.length > 0) {
        new Chart(document.getElementById('monthlyChart'), {
            type: 'doughnut',
            data: {
                labels: monthlyData.map(d => d.label),
                datasets: [{ data: monthlyData.map(d => d.amount), backgroundColor: monthlyData.map(d => d.color), borderWidth: 2, borderColor: '#fff' }]
            },
            options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { padding: 12, font: { size: 12 } } } } }
        });
    }

    const compareData = {!! json_encode($cycleComparison) !!};
    if (compareData.length > 0) {
        new Chart(document.getElementById('compareChart'), {
            type: 'bar',
            data: {
                labels: compareData.map(d => d.label),
                datasets: [{ label: 'إجمالي المصروف', data: compareData.map(d => d.total), backgroundColor: ['#C9B59C', '#8B6F4E', '#5D4E37'], borderRadius: 8, barThickness: 50 }]
            },
            options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: v => v.toLocaleString() + ' ر.س' } } } }
        });
    }
</script>
@endpush
