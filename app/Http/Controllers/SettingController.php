<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'telegram_chat_id' => Setting::getValue('telegram_chat_id', ''),
            'telegram_bot_token' => Setting::getValue('telegram_bot_token', ''),
            'api_key' => Setting::getValue('api_key', ''),
            'gemini_api_key' => Setting::getValue('gemini_api_key', ''),
            'telegram_webhook_secret' => Setting::getValue('telegram_webhook_secret', ''),
        ];

        $sharedPin = Setting::getValue('shared_pin', '');
        $sharedCategoryIds = json_decode(Setting::getValue('shared_categories', '[]'), true) ?: [];
        $sharedTransactionsLimit = (int) Setting::getValue('shared_transactions_limit', '0');
        $savingsCategoryId = Setting::getValue('savings_category_id', '');
        $overageSourceCategoryId = Setting::getValue('overage_source_category_id', '');
        $autoSettleOverages = Setting::getValue('auto_settle_overages', '0') === '1';
        $allCategories = Category::where('is_active', true)->get();

        return view('settings.index', compact('settings', 'sharedPin', 'sharedCategoryIds', 'sharedTransactionsLimit', 'savingsCategoryId', 'overageSourceCategoryId', 'autoSettleOverages', 'allCategories'));
    }

    public function updateShared(Request $request)
    {
        $request->validate(['shared_transactions_limit' => 'required|integer|in:0,3,5,10']);
        Setting::setValue('shared_pin', $request->input('shared_pin', ''));
        Setting::setValue('shared_categories', json_encode($request->input('shared_categories', [])));
        Setting::setValue('shared_transactions_limit', (string) $request->input('shared_transactions_limit', 0));

        return back()->with('success', 'تم حفظ إعدادات المشاركة');
    }

    public function update(Request $request)
    {
        $request->validate([
            'telegram_chat_id' => 'nullable|string',
            'telegram_bot_token' => 'nullable|string',
            'api_key' => 'nullable|string',
            'overage_source_category_id' => 'nullable|exists:categories,id',
            'telegram_webhook_secret' => 'nullable|string|min:32|max:256',
        ]);

        foreach (['telegram_chat_id', 'telegram_bot_token', 'api_key', 'gemini_api_key', 'savings_category_id', 'overage_source_category_id', 'telegram_webhook_secret'] as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, $request->input($key));
            }
        }
        Setting::setValue('auto_settle_overages', $request->boolean('auto_settle_overages') ? '1' : '0');

        return back()->with('success', 'تم حفظ الإعدادات');
    }

    public function setTelegramWebhook()
    {
        $secret = Setting::getValue('telegram_webhook_secret', '');
        if (mb_strlen($secret) < 32) {
            $secret = Str::random(48);
            Setting::setValue('telegram_webhook_secret', $secret);
        }

        try {
            app(TelegramService::class)->setWebhook(route('telegram.webhook'), $secret);

            return back()->with('success', 'تم ربط Webhook تيليجرام وحمايته بالرمز السري.');
        } catch (\Throwable $e) {
            return back()->with('error', 'تعذر إعداد Webhook: ' . $e->getMessage());
        }
    }

    public function testTelegram()
    {
        try {
            app(TelegramService::class)->sendMessage('✅ رسالة تجريبية - نظام متابعة المصاريف يعمل بنجاح!');
            return back()->with('success', 'تم إرسال الرسالة التجريبية');
        } catch (\Exception $e) {
            return back()->with('error', 'فشل الإرسال: ' . $e->getMessage());
        }
    }
}
