@extends('layouts.app')

@section('title', 'تعديل عملية')
@section('page-title', 'تعديل عملية')

@section('content')
    <form method="POST" action="{{ route('transactions.update', $transaction) }}">
        @csrf
        @method('PUT')

        <div class="card-main">
            <!-- Amount -->
            <div class="mb-3">
                <label class="form-label">المبلغ</label>
                <input type="number" step="0.01" name="amount" class="form-control" value="{{ old('amount', $transaction->amount) }}" required>
                @error('amount') <div class="text-danger" style="font-size:0.75rem;">{{ $message }}</div> @enderror
            </div>

            <!-- Merchant -->
            <div class="mb-3">
                <label class="form-label">الوصف / التاجر</label>
                <input type="text" name="merchant" class="form-control" value="{{ old('merchant', $transaction->merchant) }}">
            </div>

            <!-- Type -->
            <div class="mb-3">
                <label class="form-label">النوع</label>
                <div class="d-flex gap-2 flex-wrap">
                    @foreach(['purchase' => '🛍 شراء', 'transfer' => '💸 تحويل', 'atm' => '🏧 سحب', 'income' => '💰 دخل'] as $val => $label)
                        <label class="filter-pill {{ old('type', $transaction->type) === $val ? 'active' : '' }}" style="cursor:pointer;">
                            <input type="radio" name="type" value="{{ $val }}" {{ old('type', $transaction->type) === $val ? 'checked' : '' }} style="display:none;" onchange="this.closest('form').querySelectorAll('.filter-pill').forEach(p=>p.classList.remove('active')); this.closest('.filter-pill').classList.add('active');">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>

            <!-- Date -->
            <div class="mb-3">
                <label class="form-label">التاريخ</label>
                <input type="datetime-local" name="transaction_date" class="form-control" value="{{ old('transaction_date', $transaction->transaction_date->format('Y-m-d\TH:i')) }}" required>
            </div>
        </div>

        <!-- Category -->
        <div class="section-title mt-3">الفئة</div>
        <div class="card-main">
            <div class="classify-grid">
                <label class="classify-btn {{ !old('category_id', $transaction->category_id) ? 'active' : '' }}" style="cursor:pointer;{{ !old('category_id', $transaction->category_id) ? 'background:var(--accent);border-color:var(--accent);color:#fff;' : '' }}">
                    <input type="radio" name="category_id" value="" {{ !old('category_id', $transaction->category_id) ? 'checked' : '' }} style="display:none;" onchange="document.querySelectorAll('.classify-grid .classify-btn').forEach(b=>{b.classList.remove('active');b.style.background='';b.style.borderColor='';b.style.color='';}); this.closest('.classify-btn').classList.add('active');this.closest('.classify-btn').style.background='var(--accent)';this.closest('.classify-btn').style.borderColor='var(--accent)';this.closest('.classify-btn').style.color='#fff';">
                    📋<br>بدون
                </label>
                @foreach($categories as $cat)
                    <label class="classify-btn {{ old('category_id', $transaction->category_id) == $cat->id ? 'active' : '' }}" style="cursor:pointer;{{ old('category_id', $transaction->category_id) == $cat->id ? 'background:var(--accent);border-color:var(--accent);color:#fff;' : '' }}">
                        <input type="radio" name="category_id" value="{{ $cat->id }}" {{ old('category_id', $transaction->category_id) == $cat->id ? 'checked' : '' }} style="display:none;" onchange="document.querySelectorAll('.classify-grid .classify-btn').forEach(b=>{b.classList.remove('active');b.style.background='';b.style.borderColor='';b.style.color='';}); this.closest('.classify-btn').classList.add('active');this.closest('.classify-btn').style.background='var(--accent)';this.closest('.classify-btn').style.borderColor='var(--accent)';this.closest('.classify-btn').style.color='#fff';">
                        {{ $cat->icon }}<br>{{ $cat->name }}
                    </label>
                @endforeach
            </div>
        </div>

        <button type="submit" class="btn btn-accent w-100 mt-3">حفظ التعديلات</button>
        <a href="{{ route('transactions.index') }}" class="btn btn-outline w-100 mt-2">إلغاء</a>
    </form>

    <!-- Delete -->
    <form method="POST" action="{{ route('transactions.destroy', $transaction) }}" class="mt-2">
        @csrf
        @method('DELETE')
        <button type="submit" class="btn w-100" style="background:none;color:var(--danger);font-size:0.85rem;" onclick="return confirm('حذف هذه العملية؟')">حذف العملية</button>
    </form>
@endsection
