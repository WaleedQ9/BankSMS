@extends('layouts.app')

@section('title', 'إضافة عملية')
@section('page-title', 'إضافة عملية')

@section('content')
    <form method="POST" action="{{ route('transactions.store') }}">
        @csrf

        <div class="card-main">
            <!-- Amount -->
            <div class="mb-3">
                <label class="form-label">المبلغ</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount') }}" required autofocus>
                @error('amount') <div class="text-danger" style="font-size:0.75rem;">{{ $message }}</div> @enderror
            </div>

            <!-- Merchant -->
            <div class="mb-3">
                <label class="form-label">الوصف / التاجر</label>
                <input type="text" name="merchant" class="form-control" value="{{ old('merchant') }}" placeholder="مثال: مطعم، سوبرماركت...">
            </div>

            <!-- Type -->
            <div class="mb-3">
                <label class="form-label">النوع</label>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach(['purchase' => '🛍 شراء', 'transfer' => '💸 تحويل', 'atm' => '🏧 سحب', 'income' => '💰 دخل'] as $val => $label)
                        <label class="filter-pill {{ old('type', 'purchase') === $val ? 'active' : '' }}" style="cursor:pointer;">
                            <input type="radio" name="type" value="{{ $val }}" {{ old('type', 'purchase') === $val ? 'checked' : '' }} style="display:none;" onchange="this.closest('form').querySelectorAll('.filter-pill').forEach(p=>p.classList.remove('active')); this.closest('.filter-pill').classList.add('active');">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Date -->
            <div class="mb-3">
                <label class="form-label">التاريخ</label>
                <input type="datetime-local" name="transaction_date" class="form-control" value="{{ old('transaction_date', now()->format('Y-m-d\TH:i')) }}" required>
            </div>
        </div>

        <!-- Category -->
        <div class="section-title mt-3" data-category-section>الفئة (اختياري)</div>
        <div class="card-main" data-category-section>
            <div class="classify-grid">
                <label class="classify-btn {{ !old('category_id') ? 'active' : '' }}" style="cursor:pointer;{{ !old('category_id') ? 'background:var(--accent);border-color:var(--accent);color:#fff;' : '' }}">
                    <input type="radio" name="category_id" value="" {{ !old('category_id') ? 'checked' : '' }} style="display:none;" onchange="document.querySelectorAll('.classify-grid .classify-btn').forEach(b=>{b.classList.remove('active');b.style.background='';b.style.borderColor='';b.style.color='';}); this.closest('.classify-btn').classList.add('active');this.closest('.classify-btn').style.background='var(--accent)';this.closest('.classify-btn').style.borderColor='var(--accent)';this.closest('.classify-btn').style.color='#fff';">
                    📋<br>بدون
                </label>
                @foreach($categories as $cat)
                    <label class="classify-btn {{ old('category_id') == $cat->id ? 'active' : '' }}" style="cursor:pointer;{{ old('category_id') == $cat->id ? 'background:var(--accent);border-color:var(--accent);color:#fff;' : '' }}">
                        <input type="radio" name="category_id" value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'checked' : '' }} style="display:none;" onchange="document.querySelectorAll('.classify-grid .classify-btn').forEach(b=>{b.classList.remove('active');b.style.background='';b.style.borderColor='';b.style.color='';}); this.closest('.classify-btn').classList.add('active');this.closest('.classify-btn').style.background='var(--accent)';this.closest('.classify-btn').style.borderColor='var(--accent)';this.closest('.classify-btn').style.color='#fff';">
                        {{ $cat->icon }}<br>{{ $cat->name }}
                    </label>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-accent w-100 mt-3">حفظ العملية</button>
        <a href="{{ route('transactions.index') }}" class="btn btn-outline w-100 mt-2">إلغاء</a>
    </form>
@endsection

@push('scripts')
<script>
    function toggleIncomeCategory() {
        const isIncome = document.querySelector('input[name="type"]:checked')?.value === 'income';
        document.querySelectorAll('[data-category-section]').forEach(section => section.style.display = isIncome ? 'none' : '');
    }
    document.querySelectorAll('input[name="type"]').forEach(input => input.addEventListener('change', toggleIncomeCategory));
    toggleIncomeCategory();
</script>
@endpush
