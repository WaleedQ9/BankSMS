<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Services\TelegramService;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $settings = [
            'telegram_chat_id' => Setting::getValue('telegram_chat_id', ''),
            'telegram_bot_token' => Setting::getValue('telegram_bot_token', ''),
            'api_key' => Setting::getValue('api_key', ''),
            'gemini_api_key' => Setting::getValue('gemini_api_key', ''),
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
        ]);

        foreach (['telegram_chat_id', 'telegram_bot_token', 'api_key', 'gemini_api_key', 'savings_category_id'] as $key) {
            if ($request->has($key)) {
                Setting::setValue($key, $request->input($key));
            }
        }

        return back()->with('success', 'تم حفظ الإعدادات');
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
