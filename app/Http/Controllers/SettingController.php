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
        $savingsCategoryId = Setting::getValue('savings_category_id', '');
        $allCategories = Category::where('is_active', true)->get();

        return view('settings.index', compact('settings', 'sharedPin', 'sharedCategoryIds', 'savingsCategoryId', 'allCategories'));
    }

    public function updateShared(Request $request)
    {
        Setting::setValue('shared_pin', $request->input('shared_pin', ''));
        Setting::setValue('shared_categories', json_encode($request->input('shared_categories', [])));

        return back()->with('success', 'تم حفظ إعدادات المشاركة');
    }

    public function update(Request $request)
    {
        $request->validate([
            'telegram_chat_id' => 'nullable|string',
            'telegram_bot_token' => 'nullable|string',
            'api_key' => 'nullable|string',
            'telegram_webhook_secret' => 'nullable|string|min:32|max:256',
        ]);

        foreach (['telegram_chat_id', 'telegram_bot_token', 'api_key', 'gemini_api_key', 'savings_category_id', 'telegram_webhook_secret'] as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, $request->input($key));
            }
        }

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
