@extends('layouts.app')

@section('title', 'غير مصنفة')
@section('page-title', 'عمليات غير مصنفة')
@section('page-subtitle', $transactions->count() . ' عملية تنتظر التصنيف')

@section('content')
    @if(session('success'))
        <div class="alert-toast success">{{ session('success') }}</div>
    @endif

    @forelse($transactions as $tx)
        <div class="card-main">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div style="font-weight:800;font-size:1.1rem;color:var(--text-primary);">{{ number_format($tx->amount, 2) }} ريال</div>
                    <div style="font-size:0.85rem;color:var(--text-secondary);">{{ $tx->merchant ?: $tx->type }}</div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">{{ $tx->transaction_date->format('j/n/Y H:i') }}</div>
                </div>
                @php $typeIcons = ['purchase' => '🛍', 'transfer' => '💸', 'atm' => '🏧']; @endphp
                <span style="font-size:1.5rem;">{{ $typeIcons[$tx->type] ?? '📄' }}</span>
            </div>
            <div class="classify-grid">
                @foreach($categories as $cat)
                    <form method="POST" action="{{ route('transactions.classify', $tx) }}">
                        @csrf
                        <input type="hidden" name="category_id" value="{{ $cat->id }}">
                        <button type="submit" class="classify-btn w-100">{{ $cat->icon }}<br>{{ $cat->name }}</button>
                    </form>
                @endforeach
            </div>
        </div>
    @empty
        <div class="empty-state">
            <div class="icon">✅</div>
            <div>كل العمليات مصنفة</div>
        </div>
    @endforelse
@endsection
