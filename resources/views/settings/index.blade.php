@extends('layouts.app')

@section('title', 'الإعدادات')
@section('page-title', 'الإعدادات')

@section('content')
    @if (session('success'))
        <div class="alert-toast success">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert-toast error">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('settings.update') }}">
        @csrf


        <details class="card-main settings-group">
            <summary>
                <div>
                    <div class="section-title">✈️ تيليجرام</div>
                    <div class="settings-group__subtitle">ربط البوت والإشعارات والحماية</div>
                </div>
                <span class="settings-chevron">⌄</span>
            </summary>
            <div class="settings-group__body">
                <div class="section-title">Telegram Bot</div>
                <div class="mb-3">
                    <label class="form-label">Bot Token</label>
                    <input type="text" name="telegram_bot_token" class="form-control"
                        value="{{ $settings['telegram_bot_token'] }}" dir="ltr">
                </div>
                <div class="mb-3">
                    <label class="form-label">Chat ID</label>
                    <input type="text" name="telegram_chat_id" class="form-control"
                        value="{{ $settings['telegram_chat_id'] }}" dir="ltr">
                </div>
                <div class="mb-3">
                    <label class="form-label">Webhook Secret Token</label>
                    <div class="d-flex gap-2">
                        <input id="webhookSecret" type="text" name="telegram_webhook_secret" class="form-control"
                            value="{{ $settings['telegram_webhook_secret'] }}" dir="ltr" minlength="32"
                            placeholder="رمز سري بطول 32 حرفاً أو أكثر">
                        <button type="button" class="btn btn-outline"
                            onclick="document.getElementById('webhookSecret').value = Array.from(crypto.getRandomValues(new Uint8Array(36))).map(v => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789'[v % 62]).join('')">توليد</button>
                    </div>
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">احفظ الإعدادات أولاً، ثم اضغط زر
                        حماية
                        Webhook أدناه.</div>
                </div>
            </div>
        </details>

        <details class="card-main settings-group">
            <summary>
                <div>
                    <div class="section-title">🔌 الاتصال والتكامل</div>
                    <div class="settings-group__subtitle">اختصار iPhone وتحليل الرسائل</div>
                </div>
                <span class="settings-chevron">⌄</span>
            </summary>
            <div class="settings-group__body">
                <div class="section-title">API Key (iOS Shortcut)</div>
                <div class="mb-3">
                    <label class="form-label">المفتاح</label>
                    <input type="text" name="api_key" class="form-control" value="{{ $settings['api_key'] }}"
                        dir="ltr" style="font-size:0.8rem;">
                </div>
                <div class="section-title">Gemini AI (تحليل الرسائل)</div>
                <div class="mb-3">
                    <label class="form-label">Gemini API Key</label>
                    <input type="password" name="gemini_api_key" class="form-control"
                        value="{{ $settings['gemini_api_key'] }}" dir="ltr" style="font-size:0.8rem;"
                        placeholder="AIza...">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">يستخدم لتحليل رسائل SMS تلقائياً.
                        احصل
                        على مفتاح من aistudio.google.com</div>
                </div>
            </div>
        </details>

        <details class="card-main settings-group" open>
            <summary>
                <div>
                    <div class="section-title">💰 الدورة المالية والادخار</div>
                    <div class="settings-group__subtitle">الراتب والترحيل وتسوية التجاوزات</div>
                </div>
                <span class="settings-chevron">⌄</span>
            </summary>
            <div class="settings-group__body">
                <div class="section-title">رصيد البنود</div>
                <div class="mb-3">
                    <label class="form-label">فئة الادخار</label>
                    <select name="savings_category_id" class="form-select">
                        <option value="">-- اختر فئة الادخار --</option>
                        @foreach ($allCategories as $cat)
                            <option value="{{ $cat->id }}" {{ $savingsCategoryId == $cat->id ? 'selected' : '' }}>
                                {{ $cat->icon }} {{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">البنود المحددة كـ "ادخار" سيُنقل
                        متبقيها لهذه الفئة عند الأرشفة</div>
                </div>
                <hr style="border-color:var(--border);">
                <div class="mb-3">
                    <label class="d-flex align-items-center gap-2" style="cursor:pointer;font-weight:700;">
                        <input type="checkbox" name="auto_settle_overages" value="1"
                            {{ $autoSettleOverages ? 'checked' : '' }}>
                        تغطية التجاوزات تلقائياً عند فتح دورة جديدة
                    </label>
                    <label class="form-label mt-2">بند تغطية التجاوزات</label>
                    <select name="overage_source_category_id" class="form-select">
                        <option value="">-- لا يوجد --</option>
                        @foreach ($allCategories as $cat)
                            <option value="{{ $cat->id }}"
                                {{ $overageSourceCategoryId == $cat->id ? 'selected' : '' }}>
                                {{ $cat->icon }} {{ $cat->name }}</option>
                        @endforeach
                    </select>
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-top:4px;">يُخصم العجز من هذا البند أولاً، ثم
                        يطبّق عليه خيار الترحيل أو الادخار في نهاية الدورة.</div>
                </div>
                <div class="section-title">تأكيد رسائل الراتب</div>
                <label class="d-flex align-items-start gap-2 mb-3" style="cursor:pointer;">
                    <input type="radio" name="salary_cycle_confirmation_mode" value="scheduled_dates"
                        {{ $salaryCycleConfirmationMode === 'scheduled_dates' ? 'checked' : '' }}>
                    <span><strong>فتح تلقائي في 26–28</strong><small class="d-block"
                            style="color:var(--text-muted);margin-top:3px;">أي رسالة راتب في تاريخ آخر تحتاج تأكيدك من نافذة
                            الرئيسية.</small></span>
                </label>
                <label class="d-flex align-items-start gap-2" style="cursor:pointer;">
                    <input type="radio" name="salary_cycle_confirmation_mode" value="always"
                        {{ $salaryCycleConfirmationMode === 'always' ? 'checked' : '' }}>
                    <span><strong>تأكيد كل رسائل الراتب</strong><small class="d-block"
                            style="color:var(--text-muted);margin-top:3px;">لا تُفتح أي دورة إلا بعد اختيارك من
                            النافذة.</small></span>
                </label>
            </div>
        </details>

        <details class="card-main settings-group">
            <summary>
                <div>
                    <div class="section-title">🎨 المظهر</div>
                    <div class="settings-group__subtitle">اختيار تصميم التطبيق</div>
                </div>
                <span class="settings-chevron">⌄</span>
            </summary>
            <div class="settings-group__body">
                <div class="section-title">تصميم الواجهة</div>
                <label class="theme-choice">
                    <input type="radio" name="app_theme" value="classic"
                        {{ $appTheme === 'classic' ? 'checked' : '' }}>
                    <span><strong>التصميم الحالي</strong><small>ألوان دافئة وبطاقات بسيطة.</small></span>
                </label>
                <label class="theme-choice">
                    <input type="radio" name="app_theme" value="shared" {{ $appTheme === 'shared' ? 'checked' : '' }}>
                    <span><strong>التصميم المالي الأخضر</strong><small>بنفس طابع صفحة المشاركة: خلفية هادئة، تدرجات خضراء
                            وبطاقات ناعمة.</small></span>
                </label>
            </div>
        </details>

        <button type="submit" class="btn btn-accent w-100 mb-3">حفظ الإعدادات</button>
    </form>

    <details class="card-main settings-group">
        <summary>
            <div>
                <div class="section-title">👥 صفحة مشتركة</div>
                <div class="settings-group__subtitle">الرمز السري والبنود المعروضة وإعدادات العمليات</div>
            </div>
            <span class="settings-chevron">⌄</span>
        </summary>
        <div class="settings-group__body">
            <form method="POST" action="{{ route('settings.updateShared') }}">
                @csrf
                <div class="mb-3">
                    <label class="form-label">الرمز السري</label>
                    <input type="text" name="shared_pin" class="form-control" value="{{ $sharedPin }}"
                        dir="ltr" inputmode="numeric" placeholder="مثال: 1234"
                        style="text-align:center;letter-spacing:8px;font-weight:700;">
                </div>
                <div class="mb-3">
                    <label class="form-label">الفئات المعروضة</label>
                    @foreach ($allCategories as $cat)
                        <label
                            style="display:flex;align-items:center;gap:8px;padding:8px 0;border-bottom:1px solid #F3F1EE;cursor:pointer;">
                            <input type="checkbox" name="shared_categories[]" value="{{ $cat->id }}"
                                {{ in_array($cat->id, $sharedCategoryIds) ? 'checked' : '' }}>
                            <span>{{ $cat->icon }} {{ $cat->name }}</span>
                        </label>
                    @endforeach
                </div>
                <div class="mb-3">
                    <label class="form-label">عرض آخر العمليات عند الضغط على البند</label>
                    <select name="shared_transactions_limit" class="form-select">
                        <option value="0" {{ $sharedTransactionsLimit === 0 ? 'selected' : '' }}>لا تعرض العمليات
                        </option>
                        <option value="3" {{ $sharedTransactionsLimit === 3 ? 'selected' : '' }}>آخر 3 عمليات
                        </option>
                        <option value="5" {{ $sharedTransactionsLimit === 5 ? 'selected' : '' }}>آخر 5 عمليات
                        </option>
                        <option value="10" {{ $sharedTransactionsLimit === 10 ? 'selected' : '' }}>آخر 10 عمليات
                        </option>
                    </select>
                </div>
                <button type="submit" class="btn btn-accent w-100 mb-2">حفظ إعدادات المشاركة</button>
            </form>
            @if ($sharedPin)
                <div style="margin-top:8px;padding:10px;background:#F3F1EE;border-radius:10px;text-align:center;">
                    <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:4px;">رابط الصفحة المشتركة</div>
                    <div dir="ltr" style="font-size:0.75rem;font-weight:600;word-break:break-all;">
                        {{ url('/shared') }}
                    </div>
                </div>
            @endif

        </div>
    </details>

    <details class="card-main settings-group">
        <summary>
            <div>
                <div class="section-title">🛠️ أدوات تيليجرام</div>
                <div class="settings-group__subtitle">اختبار الإشعارات وتحديث Webhook</div>
            </div>
            <span class="settings-chevron">⌄</span>
        </summary>
        <div class="settings-group__body">
            <form method="POST" action="{{ route('settings.testTelegram') }}">
                @csrf
                <button type="submit" class="btn btn-outline w-100 mb-3">🔔 اختبار إرسال رسالة تيليجرام</button>
            </form>

            <form method="POST" action="{{ route('settings.telegramWebhook') }}"
                onsubmit="return confirm('سيتم تحديث Webhook لدى Telegram بالرمز السري المحفوظ. متابعة؟');">
                @csrf
                <button type="submit" class="btn btn-outline w-100">🛡️ حماية وتحديث Telegram Webhook</button>
            </form>
        </div>
    </details>

    <a href="{{ route('categories.index') }}" class="btn btn-outline w-100 mb-3">📂 إدارة الفئات</a>

    <form method="POST" action="{{ route('otp.logout') }}">
        @csrf
        <button type="submit" class="btn btn-outline w-100" style="color:var(--danger);border-color:var(--danger);">🚪
            تسجيل خروج</button>
    </form>
    <div style="text-align:center;color:var(--text-muted);font-size:.72rem;margin-top:20px;">
        الإصدار {{ config('app.version') }}
    </div>
    <div style="text-align:center;color:var(--text-muted);font-size:.72rem;margin-top:20px;">
        WaleedQ
    </div>
@endsection

@push('styles')
    <style>
        .settings-group {
            padding: 0;
            overflow: hidden;
        }

        .settings-group>summary {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 18px;
            cursor: pointer;
            list-style: none;
        }

        .settings-group>summary::-webkit-details-marker {
            display: none;
        }

        .settings-group>summary .section-title {
            margin: 0;
        }

        .settings-group__subtitle {
            font-size: .7rem;
            color: var(--text-muted);
            margin-top: 3px;
        }

        .settings-chevron {
            font-size: 1.15rem;
            color: var(--text-muted);
            transition: transform .2s ease;
        }

        .settings-group[open] .settings-chevron {
            transform: rotate(180deg);
        }

        .settings-group__body {
            padding: 0 18px 18px;
            border-top: 1px solid var(--border);
        }

        .settings-group__body>.section-title {
            margin: 18px 0 12px;
        }

        .settings-group__body>.section-title+.mb-3 {
            margin-bottom: 20px !important;
        }

        .theme-choice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px;
            margin-bottom: 8px;
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
        }

        .theme-choice:has(input:checked) {
            border-color: var(--accent);
            background: var(--accent-bg);
        }

        .theme-choice input {
            margin-top: 4px;
            accent-color: var(--accent);
        }

        .theme-choice strong,
        .theme-choice small {
            display: block;
        }

        .theme-choice small {
            margin-top: 3px;
            color: var(--text-muted);
            font-size: .72rem;
        }
    </style>
@endpush
