<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramService
{
    private string $token;
    private string $chatId;
    private string $baseUrl;

    public function __construct()
    {
        $this->token = Setting::getValue('telegram_bot_token', config('services.telegram.bot_token', ''));
        $this->chatId = Setting::getValue('telegram_chat_id', config('services.telegram.chat_id', ''));
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    public function setWebhook(string $url, string $secret): void
    {
        if (!$this->token) {
            throw new \RuntimeException('أدخل Telegram Bot Token أولاً ثم احفظ الإعدادات.');
        }

        $response = Http::timeout(15)->post("{$this->baseUrl}/setWebhook", [
            'url' => $url,
            'secret_token' => $secret,
            'allowed_updates' => ['message', 'callback_query'],
        ]);

        if (!$response->successful() || !$response->json('ok')) {
            throw new \RuntimeException($response->json('description') ?: 'رفض Telegram إعداد الـ Webhook.');
        }
    }

    /**
     * Send classification message with inline keyboard for a transaction.
     */
    public function sendClassificationMessage(Transaction $transaction): ?string
    {
        $typeLabels = [
            'purchase' => '🛍 عملية شراء',
            'transfer' => '💸 تحويل صادر',
            'atm' => '🏧 سحب صراف',
        ];

        $typeLabel = $typeLabels[$transaction->type] ?? '📄 عملية';

        $text = "{$typeLabel}\n";
        $text .= "━━━━━━━━━━━━━\n";
        if ($transaction->merchant) {
            $text .= "المحل: {$transaction->merchant}\n";
        }
        $text .= "المبلغ: " . number_format($transaction->amount, 2) . " ريال\n";
        if ($transaction->payment_method) {
            $text .= "الدفع: {$transaction->payment_method}\n";
        }
        if ($transaction->transaction_date) {
            $text .= "الوقت: " . $transaction->transaction_date->format('j/n/y H:i') . "\n";
        }
        $text .= "━━━━━━━━━━━━━\n";
        $text .= "صنّف العملية:";

        $categories = Category::where('is_active', true)->get();
        $keyboard = [];
        $row = [];

        foreach ($categories as $i => $cat) {
            $row[] = [
                'text' => "{$cat->icon} {$cat->name}",
                'callback_data' => "classify:{$transaction->id}:{$cat->id}",
            ];
            if (count($row) === 3) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        $response = Http::post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
            'reply_markup' => json_encode(['inline_keyboard' => $keyboard]),
        ]);

        if ($response->successful()) {
            $messageId = $response->json('result.message_id');
            $transaction->update(['telegram_message_id' => $messageId]);
            return (string) $messageId;
        }

        Log::error('Telegram sendMessage failed', ['response' => $response->body()]);
        return null;
    }

    /**
     * Update classification message after category is selected.
     */
    public function updateClassifiedMessage(Transaction $transaction): void
    {
        if (!$transaction->telegram_message_id) {
            return;
        }

        $category = $transaction->category;

        $text = "✅ تم التصنيف\n";
        if ($transaction->merchant) {
            $text .= "المحل: {$transaction->merchant}\n";
        }
        $text .= "المبلغ: " . number_format($transaction->amount, 2) . " ريال\n";
        $text .= "الفئة: {$category->icon} {$category->name}";

        Http::post("{$this->baseUrl}/editMessageText", [
            'chat_id' => $this->chatId,
            'message_id' => $transaction->telegram_message_id,
            'text' => $text,
        ]);
    }

    /**
     * Send a simple text message.
     */
    public function sendMessage(string $text): void
    {
        Http::post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
        ]);
    }

    /**
     * Send a message with HTML parse mode (enables copy button on code blocks).
     */
    public function sendMessageHtml(string $text): void
    {
        Http::post("{$this->baseUrl}/sendMessage", [
            'chat_id' => $this->chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
        ]);
    }

    /**
     * Answer callback query to remove loading state from button.
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = ''): void
    {
        Http::post("{$this->baseUrl}/answerCallbackQuery", [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
        ]);
    }
}
