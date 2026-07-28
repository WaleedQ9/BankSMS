@extends('layouts.app')

@section('title', 'المعاملات')
@section('page-title', 'المعاملات')

@section('content')
    <!-- Filters -->
    <div class="filter-pills mb-3">
        <a href="{{ route('transactions.index', ['filter' => 'all']) }}" class="filter-pill {{ $filter === 'all' ? 'active' : '' }}">الكل</a>
        <a href="{{ route('transactions.index', ['filter' => 'week']) }}" class="filter-pill {{ $filter === 'week' ? 'active' : '' }}">هذا الأسبوع</a>
        <a href="{{ route('transactions.index', ['filter' => 'month']) }}" class="filter-pill {{ $filter === 'month' ? 'active' : '' }}">هذا الشهر</a>
        <a href="{{ route('transactions.index', ['filter' => 'purchase']) }}" class="filter-pill {{ $filter === 'purchase' ? 'active' : '' }}">شراء</a>
        <a href="{{ route('transactions.index', ['filter' => 'transfer']) }}" class="filter-pill {{ $filter === 'transfer' ? 'active' : '' }}">تحويل</a>
        <a href="{{ route('transactions.index', ['filter' => 'atm']) }}" class="filter-pill {{ $filter === 'atm' ? 'active' : '' }}">سحب</a>
    </div>

    <!-- Floating Add Button -->
    <a href="{{ route('transactions.create') }}" style="position:fixed;bottom:calc(var(--nav-height) + 16px);right:16px;z-index:999;width:56px;height:56px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;box-shadow:0 4px 12px rgba(139,111,78,0.4);text-decoration:none;">+</a>

    @if(session('success'))
        <div class="alert-toast success">{{ session('success') }}</div>
    @endif

    <!-- Transactions List -->
    @forelse($transactions as $tx)
        @php
            $icon = $tx->category ? $tx->category->icon : ($tx->type === 'income' ? '💰' : '📄');
            $isIncome = $tx->type === 'income';
            $catName = $tx->category ? $tx->category->name : ($tx->is_classified ? '' : 'غير مصنف');
        @endphp
        <div class="tx-card">
            <div class="tx-icon">{{ $icon }}</div>
            <div class="tx-info">
                <div class="tx-merchant">{{ $tx->merchant ?: $tx->type }}</div>
                <div class="tx-date">
                    {{ $tx->transaction_date->format('j/n/y H:i') }}
                    @if($catName)
                        · {{ $catName }}
                    @endif
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <div class="tx-amount {{ $isIncome ? 'income' : 'expense' }}">
                    {{ $isIncome ? '+' : '-' }}{{ number_format($tx->amount, 2) }}
                </div>
                <div class="d-flex gap-1">
                    @if(!$isIncome)
                        <button class="btn btn-outline py-0 px-2" style="font-size:0.7rem;" data-bs-toggle="modal" data-bs-target="#classifyModal{{ $tx->id }}">تصنيف</button>
                    @endif
                    <a href="{{ route('transactions.edit', $tx) }}" class="btn btn-outline py-0 px-2" style="font-size:0.7rem;">تعديل</a>
                </div>
            </div>
        </div>

        <!-- Classify Modal -->
        @if(!$isIncome)
        <div class="modal fade" id="classifyModal{{ $tx->id }}" tabindex="-1">
            <div class="modal-dialog modal-dialog-centered modal-sm">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h6 class="modal-title">تصنيف: {{ $tx->merchant }}</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="classify-grid">
                            @foreach($categories as $cat)
                                <form method="POST" action="{{ route('transactions.classify', $tx) }}">
                                    @csrf
                                    <input type="hidden" name="category_id" value="{{ $cat->id }}">
                                    <button type="submit" class="classify-btn w-100 {{ $tx->category_id == $cat->id ? 'active' : '' }}"
                                            style="{{ $tx->category_id == $cat->id ? 'background:var(--accent);border-color:var(--accent);' : '' }}">
                                        {{ $cat->icon }}<br>{{ $cat->name }}
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    @empty
        <div class="empty-state">
            <div class="icon">📭</div>
            <div>لا توجد معاملات</div>
        </div>
    @endforelse

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-3">
        {{ $transactions->appends(['filter' => $filter])->links('pagination::simple-bootstrap-5') }}
    </div>
@endsection
