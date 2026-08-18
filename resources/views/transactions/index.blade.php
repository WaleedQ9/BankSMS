@extends('layouts.app')

@section('title', 'المعاملات')
@section('page-title', 'المعاملات')

@section('content')
    <style>
        .transactions-pager {
            display: flex;
            justify-content: center;
            margin: 24px 0 8px;
        }

        .transactions-pager__list {
            display: flex;
            direction: rtl;
            align-items: center;
            gap: 6px;
            margin: 0;
            padding: 7px;
            list-style: none;
            background: #fff;
            border: 1px solid #E8E2DB;
            border-radius: 18px;
            box-shadow: 0 5px 14px rgba(62, 48, 33, .06);
        }

        .transactions-pager__link,
        .transactions-pager__ellipsis {
            display: grid;
            place-items: center;
            min-width: 34px;
            height: 34px;
            padding: 0 8px;
            border-radius: 11px;
            color: var(--text-muted);
            font-size: .8rem;
            font-weight: 700;
            text-decoration: none;
        }

        .transactions-pager__link:hover {
            background: #F4EFE9;
            color: var(--accent);
        }

        .transactions-pager__link.is-current {
            color: #fff;
            background: var(--accent);
            box-shadow: 0 3px 8px rgba(139, 111, 78, .28);
        }

        .transactions-pager__link.is-disabled {
            opacity: .32;
            pointer-events: none;
        }

        .transactions-pager__arrow {
            font-size: 1.2rem;
            line-height: 1;
        }

        .transactions-pager__ellipsis {
            min-width: 20px;
            padding: 0;
            letter-spacing: 1px;
        }
    </style>
    <!-- Filters -->
    <div class="filter-pills mb-3">
        <a href="{{ route('transactions.index', ['filter' => 'all']) }}"
            class="filter-pill {{ $filter === 'all' ? 'active' : '' }}">الكل</a>
        <a href="{{ route('transactions.index', ['filter' => 'week']) }}"
            class="filter-pill {{ $filter === 'week' ? 'active' : '' }}">الفترة الحالية</a>
        <a href="{{ route('transactions.index', ['filter' => 'month']) }}"
            class="filter-pill {{ $filter === 'month' ? 'active' : '' }}">هذا الشهر</a>
        <a href="{{ route('transactions.index', ['filter' => 'purchase']) }}"
            class="filter-pill {{ $filter === 'purchase' ? 'active' : '' }}">شراء</a>
        <a href="{{ route('transactions.index', ['filter' => 'transfer']) }}"
            class="filter-pill {{ $filter === 'transfer' ? 'active' : '' }}">تحويل</a>
        <a href="{{ route('transactions.index', ['filter' => 'atm']) }}"
            class="filter-pill {{ $filter === 'atm' ? 'active' : '' }}">سحب</a>
    </div>

    <!-- Floating Add Button -->
    <a href="{{ route('transactions.create') }}"
        style="position:fixed;bottom:calc(var(--nav-height) + 16px);right:16px;z-index:999;width:56px;height:56px;border-radius:50%;background:var(--accent);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.8rem;box-shadow:0 4px 12px rgba(139,111,78,0.4);text-decoration:none;">+</a>

    @if (session('success'))
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
                    {{ $tx->transaction_date->format('j/n/Y H:i') }}
                    @if ($catName)
                        · {{ $catName }}
                    @endif
                </div>
            </div>
            <div class="d-flex flex-column align-items-end gap-1">
                <div class="tx-amount {{ $isIncome ? 'income' : 'expense' }}">
                    {{ $isIncome ? '+' : '-' }}{{ number_format($tx->amount, 2) }}
                </div>
                <div class="d-flex gap-1">
                    @if (!$isIncome)
                        <button class="btn btn-outline py-0 px-2" style="font-size:0.7rem;" data-bs-toggle="modal"
                            data-bs-target="#classifyModal{{ $tx->id }}">تصنيف</button>
                    @endif
                    <a href="{{ route('transactions.edit', $tx) }}" class="btn btn-outline py-0 px-2"
                        style="font-size:0.7rem;">تعديل</a>
                </div>
            </div>
        </div>

        <!-- Classify Modal -->
        @if (!$isIncome)
            <div class="modal fade" id="classifyModal{{ $tx->id }}" tabindex="-1">
                <div class="modal-dialog modal-dialog-centered modal-sm">
                    <div class="modal-content">
                        <div class="modal-header py-2">
                            <h6 class="modal-title">تصنيف: {{ $tx->merchant }}</h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="classify-grid">
                                @foreach ($categories as $cat)
                                    <form method="POST" action="{{ route('transactions.classify', $tx) }}">
                                        @csrf
                                        <input type="hidden" name="category_id" value="{{ $cat->id }}">
                                        <button type="submit"
                                            class="classify-btn w-100 {{ $tx->category_id == $cat->id ? 'active' : '' }}"
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

    @if ($transactions->hasPages())
        @php
            $pager = $transactions->appends(['filter' => $filter]);
            $currentPage = $pager->currentPage();
            $lastPage = $pager->lastPage();
            $startPage = max(1, $currentPage - 1);
            $endPage = min($lastPage, $currentPage + 1);
        @endphp
        <nav class="transactions-pager" aria-label="تنقل صفحات المعاملات">
            <ul class="transactions-pager__list">
                <li>
                    <a class="transactions-pager__link transactions-pager__arrow {{ $pager->onFirstPage() ? 'is-disabled' : '' }}"
                        href="{{ $pager->previousPageUrl() ?: '#' }}" aria-label="الصفحة السابقة">‹</a>
                </li>

                @if ($currentPage > 3)
                    <li><a class="transactions-pager__link" href="{{ $pager->url(1) }}">1</a></li>
                @endif
                @if ($currentPage > 4)
                    <li><span class="transactions-pager__ellipsis">•••</span></li>
                @endif

                @for ($page = $startPage; $page <= $endPage; $page++)
                    <li>
                        <a class="transactions-pager__link {{ $page === $currentPage ? 'is-current' : '' }}"
                            href="{{ $pager->url($page) }}"
                            {{ $page === $currentPage ? 'aria-current=page' : '' }}>{{ $page }}</a>
                    </li>
                @endfor

                @if ($currentPage < $lastPage - 3)
                    <li><span class="transactions-pager__ellipsis">•••</span></li>
                @endif
                @if ($currentPage < $lastPage - 2)
                    <li><a class="transactions-pager__link" href="{{ $pager->url($lastPage) }}">{{ $lastPage }}</a>
                    </li>
                @endif

                <li>
                    <a class="transactions-pager__link transactions-pager__arrow {{ $pager->hasMorePages() ? '' : 'is-disabled' }}"
                        href="{{ $pager->nextPageUrl() ?: '#' }}" aria-label="الصفحة التالية">›</a>
                </li>
            </ul>
        </nav>
        <div style="text-align:center;color:var(--text-muted);font-size:.72rem;margin-top:8px;">
            عرض {{ $pager->firstItem() }}–{{ $pager->lastItem() }} من إجمالي {{ $pager->total() }} عملية
        </div>
    @endif
@endsection
