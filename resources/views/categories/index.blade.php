@extends('layouts.app')

@section('title', 'إدارة الفئات')
@section('page-title', 'إدارة الفئات')
@section('page-subtitle', $categories->count() . ' فئة')

@section('content')
    @if(session('success'))
        <div class="alert-toast success">{{ session('success') }}</div>
    @endif

    <!-- Monthly Income -->
    <div class="card-main mb-3">
        <div class="section-title">الدخل الشهري</div>
        <form method="POST" action="{{ route('categories.updateIncome') }}" class="d-flex align-items-center gap-2">
            @csrf
            <input type="number" name="monthly_income" value="{{ $monthlyIncome }}" class="form-control" style="max-width:160px;font-weight:700;" min="0" step="100">
            <span style="font-size:0.85rem;color:var(--text-muted);">ريال</span>
            <button type="submit" class="btn btn-accent btn-sm px-3">حفظ</button>
        </form>
    </div>

    <!-- Budget Summary -->
    <div class="card-main mb-3" style="background: linear-gradient(135deg, #f5f0eb, #fff);">
        <div class="d-flex justify-content-between mb-2">
            <span style="font-size:0.85rem;color:var(--text-secondary);">الدخل الشهري</span>
            <span style="font-weight:700;">{{ number_format($monthlyIncome, 0) }} ريال</span>
        </div>
        <div class="d-flex justify-content-between mb-2">
            <span style="font-size:0.85rem;color:var(--text-secondary);">إجمالي الميزانيات</span>
            <span style="font-weight:700;">{{ number_format($totalBudget, 0) }} ريال</span>
        </div>
        <hr style="border-color:var(--border);margin:8px 0;">
        <div class="d-flex justify-content-between">
            <span style="font-size:0.9rem;font-weight:700;">المتبقي بدون تخصيص</span>
            <span style="font-weight:800;font-size:1.1rem;color:{{ $remaining >= 0 ? 'var(--success, #059669)' : 'var(--danger, #DC2626)' }};">{{ number_format($remaining, 0) }} ريال</span>
        </div>
        @if($monthlyIncome > 0)
            <div class="budget-progress mt-2">
                <div class="fill" style="width: {{ min(round(($totalBudget / $monthlyIncome) * 100), 100) }}%; background: {{ $remaining >= 0 ? '#8B6F4E' : '#DC2626' }};"></div>
            </div>
            <div style="text-align:left;font-size:0.7rem;color:var(--text-muted);">{{ round(($totalBudget / $monthlyIncome) * 100) }}% مخصص</div>
        @endif
    </div>

    <!-- Add New Category -->
    <div class="card-main mb-4">
        <div class="section-title">إضافة فئة جديدة</div>
        <form method="POST" action="{{ route('categories.store') }}">
            @csrf
            <div class="row g-2 align-items-end">
                <div class="col-2">
                    <label class="form-label">أيقونة</label>
                    <input type="text" name="icon" class="form-control text-center" placeholder="🛒" required>
                </div>
                <div class="col-4">
                    <label class="form-label">الاسم</label>
                    <input type="text" name="name" class="form-control" placeholder="اسم الفئة" required>
                </div>
                <div class="col-3">
                    <label class="form-label">الميزانية</label>
                    <input type="number" name="monthly_amount" class="form-control" placeholder="0" min="0" step="50" value="0">
                </div>
                <div class="col-1">
                    <label class="form-label">لون</label>
                    <input type="color" name="color" class="form-control form-control-color p-1" value="#8B6F4E" style="height:38px;">
                </div>
                <div class="col-2">
                    <button type="submit" class="btn btn-accent w-100">إضافة</button>
                </div>
            </div>
            <div class="d-flex align-items-center gap-3 mt-2" style="font-size:0.8rem;">
                <span style="color:var(--text-muted);">المتبقي:</span>
                <label class="d-flex align-items-center gap-1" style="cursor:pointer;">
                    <input type="radio" name="rollover_type" value="rollover" checked style="accent-color:#059669;"> 🟢 يرحّل
                </label>
                <label class="d-flex align-items-center gap-1" style="cursor:pointer;">
                    <input type="radio" name="rollover_type" value="savings" style="accent-color:#3B82F6;"> 🔵 ادخار
                </label>
            </div>
        </form>
    </div>

    <!-- Categories List -->
    @foreach($categories as $cat)
        <div class="card-main" style="border-right: 4px solid {{ $cat->color }}; opacity: {{ $cat->is_active ? '1' : '0.5' }};">
            <form method="POST" action="{{ route('categories.update', $cat) }}">
                @csrf
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <input type="text" name="icon" value="{{ $cat->icon }}" class="form-control text-center p-1" style="width:45px;font-size:1.3rem;background:transparent;border:none;">
                    <input type="text" name="name" value="{{ $cat->name }}" class="form-control" style="flex:1;min-width:100px;font-weight:700;">
                    <input type="color" name="color" value="{{ $cat->color }}" class="form-control form-control-color p-1" style="width:40px;height:38px;">
                    <div class="d-flex align-items-center gap-1" style="min-width:130px;">
                        <input type="number" name="monthly_amount" value="{{ $cat->budget->monthly_amount ?? 0 }}" class="form-control py-1" style="width:90px;font-size:0.85rem;" min="0" step="50">
                        <span style="font-size:0.7rem;color:var(--text-muted);">ر.س</span>
                    </div>
                    <label class="d-flex align-items-center gap-1" style="font-size:0.7rem;color:var(--text-muted);cursor:pointer;white-space:nowrap;">
                        <input type="checkbox" name="show_in_weekly" value="1" {{ $cat->show_in_weekly ? 'checked' : '' }} style="accent-color:var(--accent);">
                        ضمن خطة الصرف
                    </label>
                    <select name="rollover_type" class="form-select py-0 px-1" style="width:auto;font-size:0.7rem;height:28px;">
                        <option value="rollover" {{ ($cat->rollover_type ?? 'rollover') === 'rollover' ? 'selected' : '' }}>🟢 يرحّل</option>
                        <option value="savings" {{ ($cat->rollover_type ?? 'rollover') === 'savings' ? 'selected' : '' }}>🔵 ادخار</option>
                    </select>
                    <button type="submit" class="btn btn-outline py-1 px-2" style="font-size:0.75rem;">حفظ</button>
                </div>
            </form>
            <div class="d-flex justify-content-between align-items-center mt-2" style="font-size:0.75rem;color:var(--text-muted);">
                <div>
                    <span>{{ $cat->transactions()->count() }} عملية</span>
                    @if(($cat->carried_balance ?? 0) > 0)
                        <span style="color:var(--success);margin-right:8px;">مرحّل: {{ number_format($cat->carried_balance, 0) }}</span>
                    @endif
                </div>
                <div class="d-flex gap-3">
                    <form method="POST" action="{{ route('categories.toggle', $cat) }}" class="d-inline">
                        @csrf
                        <button type="submit" class="btn p-0 border-0" style="font-size:0.75rem;color:{{ $cat->is_active ? 'var(--danger)' : 'var(--success)' }};">
                            {{ $cat->is_active ? 'تعطيل' : 'تفعيل' }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('categories.destroy', $cat) }}" class="d-inline" onsubmit="return confirm('حذف فئة {{ $cat->name }}؟ العمليات في الشهر الحالي سترجع غير مصنفة.')">
                        @csrf
                        <button type="submit" class="btn p-0 border-0" style="font-size:0.75rem;color:var(--danger);">حذف</button>
                    </form>
                </div>
            </div>
        </div>
    @endforeach
@endsection
