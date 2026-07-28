@extends('layouts.app')

@section('title', 'الإعدادات')
@section('page-title', 'الإعدادات')

@section('content')
    @if(session('success'))
        <div class="alert-toast success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-toast error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf

        <div class="card-main">
            <div class="section-title">Telegram Bot</div>
            <div class="mb-3">
                <label class="form-label">Bot Token</label>
                <input type="text" name="telegram_bot_token" class="form-control" value="{{ $settings['telegram_bot_token'] }}" dir="ltr">
            </div>
            <div class="mb-3">
                <label class="form-label">Chat ID</label>
                <input type="text" name="telegram_chat_id" class="form-control" value="{{ $settings['telegram_chat_id'] }}" dir="ltr">
            </div>
        </div>

        <div class="card-main">
            <div class="section-title">API Key (iOS Shortcut)</div>
            <div class="mb-3">
                <label class="form-label">المفتاح</label>
                <input type="text" name="api_key" class="form-control" value="{{ $settings['api_key'] }}" dir="ltr" style="font-size:0.8rem;">
            </div>
        </div>

        <div class="card-main">
            <div class="section-title">Gemini AI (تحليل الرسائل)</div>
            <div class="mb-3">
                <label class="form-label">Gemini API Key</label>
                <input type="password" name="gemini_api_key" class="form-control" value="{{ $settings['gemini_api_key'] }}" dir="ltr" style="font-size:0.8rem;" placeholder="AIza...">
                <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">يستخدم لتحليل رسائل SMS تلقائياً. احصل على مفتاح من aistudio.google.com</div>
            </div>
        </div>

        <div class="card-main">
            <div class="section-title">رصيد البنود</div>
            <div class="mb-3">
                <label class="form-label">فئة الادخار</label>
                <select name="savings_category_id" class="form-select">
                    <option value="">-- اختر فئة الادخار --</option>
                    @foreach($allCategories as $cat)
                        <option value="{{ $cat->id }}" {{ $savingsCategoryId == $cat->id ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                    @endforeach
                </select>
                <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">البنود المحددة كـ "ادخار" سيُنقل متبقيها لهذه الفئة عند الأرشفة</div>
            </div>
        </div>

        <button type="submit" class="btn btn-accent w-100 mb-3">حفظ الإعدادات</button>
    </form>

    <div class="card-main">
        <div class="section-title">صفحة مشتركة</div>
        <form method="POST" action="{{ route('settings.updateShared') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label">الرمز السري</label>
                <input type="text" name="shared_pin" class="form-control" value="{{ $sharedPin }}" dir="ltr" inputmode="numeric" placeholder="مثال: 1234" style="text-align:center;letter-spacing:8px;font-weight:700;">
            </div>
            <div class="mb-3">
                <label class="form-label">الفئات المعروضة</label>
                @foreach($allCategories as $cat)
                    <label style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #F3F1EE;cursor:pointer;">
                        <input type="checkbox" name="shared_categories[]" value="{{ $cat->id }}" {{ in_array($cat->id, $sharedCategoryIds) ? 'checked' : '' }}>
                        <span>{{ $cat->icon }} {{ $cat->name }}</span>
                    </label>
                @endforeach
            </div>
            <button type="submit" class="btn btn-accent w-100 mb-2">حفظ إعدادات المشاركة</button>
        </form>
        @if($sharedPin)
            <div style="margin-top:8px;padding:10px;background:#F3F1EE;border-radius:10px;text-align:center;">
                <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:4px;">رابط الصفحة المشتركة</div>
                <div dir="ltr" style="font-size:0.75rem;font-weight:600;word-break:break-all;">{{ url('/shared') }}</div>
            </div>
        @endif
    </div>

    <form method="POST" action="{{ route('settings.testTelegram') }}">
        @csrf
        <button type="submit" class="btn btn-outline w-100 mb-3">🔔 اختبار إرسال رسالة تيليجرام</button>
    </form>

    <a href="{{ route('categories.index') }}" class="btn btn-outline w-100 mb-3">📂 إدارة الفئات</a>

    <form method="POST" action="{{ route('otp.logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline w-100" style="color:var(--danger);border-color:var(--danger);">🚪 تسجيل خروج</button>
    </form>
@endsection
