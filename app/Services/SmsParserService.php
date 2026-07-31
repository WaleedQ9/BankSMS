<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SmsPattern;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsParserService
{
    /**
     * Parse an SMS message. Tries AI first, falls back to regex.
     */
    public function parse(string $message): ?array
    {
        $message = trim($message);

        // Declined/failed transactions — ignore immediately
        if (preg_match('/رصيد غير كافي|عملية مرفوضة|declined|insufficient/ui', $message)) {
            return null;
        }

        $hash = $this->getPatternHash($message);

        // 1. Check cache
        $cached = SmsPattern::where('pattern_hash', $hash)->first();
        if ($cached) {
            Log::info('SMS pattern found in cache', ['type' => $cached->type]);
            return [
                'type' => $cached->type,
                'amount' => $this->extractAmount($message) ?? $this->extractAmountShort($message),
                'merchant' => $this->extractMerchantGeneral($message) ?? $cached->merchant,
                'card_last4' => $this->extractCardLast4($message),
                'payment_method' => $cached->payment_method,
                'transaction_date' => $this->extractDate($message),
            ];
        }

        // 2. Try Gemini AI
        $apiKey = Setting::getValue('gemini_api_key', '');
        if (!empty($apiKey)) {
            try {
                $result = $this->parseWithAI($message, $apiKey);
                if ($result) {
                    $this->savePattern($hash, $result, $message);
                    return $result;
                }
            } catch (\Exception $e) {
                Log::warning('AI SMS parsing failed, using regex', ['error' => $e->getMessage()]);
            }
        }

        // 3. Fallback to regex
        $result = $this->parseWithRegex($message);
        if ($result) {
            $this->savePattern($hash, $result, $message);
        }

        return $result;
    }

    private function getPatternHash(string $message): string
    {
        $normalized = $message;
        // Remove dates: 2026-07-11 13:22 or 28/06/2026 01:16 or 3/7/26 18:57
        $normalized = preg_replace('/\d{4}-\d{1,2}-\d{1,2}\s+\d{1,2}:\d{2}(:\d{2})?/', '##DATE##', $normalized);
        $normalized = preg_replace('/\d{1,2}\/\d{1,2}\/\d{2,4}\s+\d{1,2}:\d{2}(:\d{2})?/', '##DATE##', $normalized);
        // Remove amounts: 15.00 SAR, 1,500 SAR, بـ15 SAR, SAR 1435.08
        $normalized = preg_replace('/[0-9,]+\.?\d*\s*(?:SAR|ريال)/', '##AMT##', $normalized);
        $normalized = preg_replace('/(?:SAR|ريال)\s*[0-9,]+\.?\d*/', '##AMT##', $normalized);
        // Remove card last 4 digits after فيزا/مدى/**
        $normalized = preg_replace('/(?:فيزا|مدى|visa|mada|\*{1,2})\d{4}/ui', '##CARD##', $normalized);
        // Remove merchant name: منXXX or لدى: XXX
        $normalized = preg_replace('/من\s*.+?(?:\n|$)/u', "من##MERCHANT##\n", $normalized);
        $normalized = preg_replace('/لدى[:\s]*.+?(?:\n|$)/u', "لدى##MERCHANT##\n", $normalized);
        // Remove transfer recipient: الى/إلى: XXX
        $normalized = preg_replace('/(?:الى|إلى)[:\s]*.+?(?:\n|$)/u', "الى##MERCHANT##\n", $normalized);

        return md5($normalized);
    }

    private function savePattern(string $hash, array $result, string $message): void
    {
        try {
            SmsPattern::updateOrCreate(
                ['pattern_hash' => $hash],
                [
                    'type' => $result['type'],
                    'merchant' => $result['merchant'],
                    'payment_method' => $result['payment_method'],
                    'raw_example' => mb_substr($message, 0, 500),
                ]
            );
            Log::info('SMS pattern saved', ['type' => $result['type'], 'merchant' => $result['merchant']]);
        } catch (\Exception $e) {
            Log::warning('Failed to save SMS pattern', ['error' => $e->getMessage()]);
        }
    }

    private function extractAmountShort(string $msg): ?float
    {
        if (preg_match('/بـ\s*([0-9,]+\.?[0-9]*)\s*(?:SAR|ريال)/u', $msg, $m)) {
            return $this->cleanAmount($m[1]);
        }
        return null;
    }

    private function parseWithAI(string $message, string $apiKey): ?array
    {
        $prompt = <<<'PROMPT'
حلل رسالة SMS البنكية التالية واستخرج المعلومات بصيغة JSON فقط بدون أي نص إضافي.

الأنواع المتاحة:
- purchase: مشتريات/شراء نقاط بيع/مشتريات إنترنت
- atm: سحب صراف/ATM
- transfer: حوالة صادرة/تحويل
- income: حوالة واردة/إيداع/راتب
- ignore: أقساط تمويل/رسائل إعلانية/أي شيء آخر

أرجع JSON بهذا الشكل:
{"type":"purchase","amount":25.00,"merchant":"اسم المتجر","card_last4":"4365","payment_method":"مدى, Apple Pay","transaction_date":"2026-07-03 18:57"}

ملاحظات:
- amount: رقم عشري
- merchant: اسم التاجر/المستلم/مكان السحب/المصدر (للدخل)
- card_last4: آخر 4 أرقام (null إذا غير موجود)
- payment_method: مثل "مدى", "Apple Pay", "بطاقة ائتمان" (null إذا غير موجود)
- transaction_date: بصيغة "YYYY-MM-DD HH:mm" (null إذا غير موجود)
- إذا كان النوع ignore أرجع: {"type":"ignore"}
PROMPT;

        $response = Http::timeout(15)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key={$apiKey}",
            [
                'contents' => [
                    ['parts' => [['text' => $prompt . "\n\nالرسالة:\n" . $message]]],
                ],
            ]
        );

        if (!$response->successful()) {
            Log::warning('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        }

        $text = $response->json('candidates.0.content.parts.0.text', '');
        if (preg_match('/\{[^}]+\}/s', $text, $m)) {
            $data = json_decode($m[0], true);
            if (!$data || ($data['type'] ?? '') === 'ignore') {
                return null;
            }

            return [
                'type' => $data['type'],
                'amount' => isset($data['amount']) ? (float) $data['amount'] : null,
                'merchant' => $data['merchant'] ?? null,
                'card_last4' => $data['card_last4'] ?? null,
                'payment_method' => $data['payment_method'] ?? null,
                'transaction_date' => isset($data['transaction_date']) ? Carbon::parse($data['transaction_date']) : null,
            ];
        }

        return null;
    }

    /**
     * Regex-based parsing (fallback).
     */
    private function parseWithRegex(string $message): ?array
    {
        if ($this->isPurchase($message)) {
            return $this->parsePurchase($message);
        }
        if ($this->isAtm($message)) {
            return $this->parseAtm($message);
        }
        if ($this->isTransferOutgoing($message)) {
            return $this->parseTransferOutgoing($message);
        }
        if ($this->isIncome($message)) {
            return $this->parseIncome($message);
        }
        return null;
    }

    // ─── Type Detection ───

    private function isPurchase(string $msg): bool
    {
        return (bool) preg_match('/نقاط\s*(?:ال)?بيع|مشتريات\s*إنترنت|^شراء\b/um', $msg);
    }

    private function isAtm(string $msg): bool
    {
        return (bool) preg_match('/سحب\s*صراف|سحب\s*ATM/ui', $msg);
    }

    private function isTransferOutgoing(string $msg): bool
    {
        return (bool) preg_match('/حوالة\s*(?:صادرة|محلية\s*صادرة)/u', $msg);
    }

    private function isIncome(string $msg): bool
    {
        return (bool) preg_match('/حوالة\s*واردة|إيداع|راتب|credited/ui', $msg);
    }

    // ─── Parsers ───

    private function parsePurchase(string $msg): array
    {
        // Two formats:
        // Format 1 (standard): مشتريات نقاط البيع / مشتريات إنترنت
        //   بطاقة: **4365; مدى, Apple Pay
        //   مبلغ: 25.00 SAR
        //   لدى: merchant name
        //   3/7/26 18:57
        //
        // Format 2 (short): شراء نقاط بيع
        //   فيزا0699 Apple Pay
        //   بـ14 SAR
        //   منYAQOT AHM
        //   2026-07-09 19:29

        $isShortFormat = (bool) preg_match('/^شراء\b/um', $msg);

        if ($isShortFormat) {
            return $this->parsePurchaseShort($msg);
        }

        return [
            'type' => 'purchase',
            'amount' => $this->extractAmount($msg),
            'merchant' => $this->extractMerchant($msg),
            'card_last4' => $this->extractCardLast4($msg),
            'payment_method' => $this->extractPaymentMethod($msg),
            'transaction_date' => $this->extractDate($msg),
        ];
    }

    private function parsePurchaseShort(string $msg): array
    {
        // Card: فيزا0699 or مدى4365
        $cardLast4 = null;
        if (preg_match('/(?:فيزا|مدى|visa|mada)(\d{4})/ui', $msg, $m)) {
            $cardLast4 = $m[1];
        }

        // Amount: بـ14 SAR or بـ46 SAR
        $amount = null;
        if (preg_match('/بـ\s*([0-9,]+\.?[0-9]*)\s*(?:SAR|ريال)/u', $msg, $m)) {
            $amount = $this->cleanAmount($m[1]);
        }

        // Merchant: منYAQOT AHM
        $merchant = null;
        if (preg_match('/من\s*(.+?)(?:\n|$)/u', $msg, $m)) {
            // Avoid matching "من حساب" or "من :*"
            $val = trim($m[1]);
            if (!preg_match('/^[\s:*\d]/u', $val) && mb_strlen($val) > 1) {
                $merchant = $val;
            }
        }

        // Payment method
        $paymentMethod = $this->extractPaymentMethod($msg);

        return [
            'type' => 'purchase',
            'amount' => $amount,
            'merchant' => $merchant,
            'card_last4' => $cardLast4,
            'payment_method' => $paymentMethod,
            'transaction_date' => $this->extractDate($msg),
        ];
    }

    private function parseAtm(string $msg): array
    {
        // Merchant = location name from مكان السحب
        $merchant = null;
        if (preg_match('/مكان\s*السحب[:\s]*(.+?)(?:\n|$)/u', $msg, $m)) {
            $merchant = trim($m[1]);
        }

        return [
            'type' => 'atm',
            'amount' => $this->extractAmount($msg),
            'merchant' => $merchant,
            'card_last4' => $this->extractCardLast4($msg),
            'payment_method' => $this->extractPaymentMethod($msg),
            'transaction_date' => $this->extractDate($msg),
        ];
    }

    private function parseTransferOutgoing(string $msg): array
    {
        // Recipient from الى: or الى:
        $merchant = null;
        if (preg_match('/(?:الى|إلى)[:\s]*(.+?)(?:\n|$)/u', $msg, $m)) {
            $val = trim($m[1]);
            if (!preg_match('/^\*|^xx/i', $val)) {
                $merchant = $val;
            }
        }

        // Amount: بـ:1500 SAR or مبلغ:750 SAR
        $amount = $this->extractAmount($msg);
        if (!$amount && preg_match('/بـ[:\s]*([0-9,]+\.?[0-9]*)\s*(?:SAR|ريال)/u', $msg, $m)) {
            $amount = $this->cleanAmount($m[1]);
        }

        return [
            'type' => 'transfer',
            'amount' => $amount,
            'merchant' => $merchant,
            'card_last4' => null,
            'payment_method' => null,
            'transaction_date' => $this->extractDate($msg),
        ];
    }

    private function parseIncome(string $msg): array
    {
        // Source from من: or عبر:
        $merchant = null;
        if (preg_match('/واردة\s*(راتب|محلية)/u', $msg, $m)) {
            $merchant = $m[1] === 'راتب' ? 'راتب' : null;
        }
        if (!$merchant && preg_match('/من[:\s]*([A-Z].+?)(?:\n|$)/u', $msg, $m)) {
            $merchant = trim($m[1]);
        }

        // Amount: بـ:1 SAR or مبلغ: 15527.64 SAR
        $amount = $this->extractAmount($msg);
        if (!$amount && preg_match('/بـ[:\s]*([0-9,]+\.?[0-9]*)\s*(?:SAR|ريال)/u', $msg, $m)) {
            $amount = $this->cleanAmount($m[1]);
        }

        return [
            'type' => 'income',
            'amount' => $amount,
            'merchant' => $merchant,
            'card_last4' => null,
            'payment_method' => null,
            'transaction_date' => $this->extractDate($msg),
        ];
    }

    // ─── Field Extractors ───

    private function extractAmount(string $msg): ?float
    {
        // مبلغ: 25.00 SAR | المبلغ المدفوع: 3,533.21 SAR
        if (preg_match('/(?:مبلغ|المبلغ)[:\s]+([0-9,]+\.?[0-9]*)\s*(?:SAR|ريال)?/u', $msg, $m)) {
            return $this->cleanAmount($m[1]);
        }
        // بـ:1500 SAR (fallback)
        if (preg_match('/بـ[:\s]*([0-9,]+\.?[0-9]*)\s*(?:SAR|ريال)/u', $msg, $m)) {
            return $this->cleanAmount($m[1]);
        }
        return null;
    }

    private function extractMerchant(string $msg): ?string
    {
        // لدى: merchant name
        if (preg_match('/لدى[:\s]*(.+?)(?:\n|$)/u', $msg, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractMerchantGeneral(string $msg): ?string
    {
        // من + merchant (short format)
        if (preg_match('/من\s*(.+?)(?:\n|$)/u', $msg, $m)) {
            $val = trim($m[1]);
            if (!preg_match('/^[\s:*\d]/u', $val) && mb_strlen($val) > 1 && !preg_match('/^حساب/u', $val)) {
                return $val;
            }
        }
        // لدى: merchant (standard format)
        if (preg_match('/لدى[:\s]*(.+?)(?:\n|$)/u', $msg, $m)) {
            return trim($m[1]);
        }
        // مكان السحب (ATM)
        if (preg_match('/مكان\s*السحب[:\s]*(.+?)(?:\n|$)/u', $msg, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function extractCardLast4(string $msg): ?string
    {
        // **4365 or **0699
        if (preg_match('/\*{1,2}(\d{4})/u', $msg, $m)) {
            return $m[1];
        }
        // فيزا0699
        if (preg_match('/(?:فيزا|مدى|visa|mada)(\d{4})/ui', $msg, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractPaymentMethod(string $msg): ?string
    {
        if (preg_match('/Apple\s*Pay/i', $msg)) {
            $type = 'Apple Pay';
            if (preg_match('/مدى|mada/iu', $msg)) return 'مدى, Apple Pay';
            if (preg_match('/الإئتمانية|الائتمانية|فيزا|visa/iu', $msg)) return 'بطاقة ائتمان, Apple Pay';
            return $type;
        }
        if (preg_match('/مدى|mada/iu', $msg)) return 'مدى';
        if (preg_match('/الإئتمانية|الائتمانية|فيزا|visa/iu', $msg)) return 'بطاقة ائتمان';
        return null;
    }

    private function extractDate(string $msg): ?Carbon
    {
        // Format: 2026-07-09 19:29 or 2026/07/29 08:49
        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})\s+(\d{1,2}:\d{2})/', $msg, $m)) {
            return $this->parseDate($m[1], $m[2], $m[3], $m[4]);
        }

        // Format: 28/06/2026 01:16:22
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{4})\s+(\d{1,2}:\d{2})/', $msg, $m)) {
            return $this->parseDate($m[3], $m[2], $m[1], $m[4]);
        }

        // Format: 3/7/26 18:57 (d/m/yy)
        if (preg_match('/(\d{1,2})\/(\d{1,2})\/(\d{2})\s+(\d{1,2}:\d{2})/', $msg, $m)) {
            $year = '20' . $m[3];
            return $this->parseDate($year, $m[2], $m[1], $m[4]);
        }

        return null;
    }

    // ─── Helpers ───

    private function cleanAmount(string $amount): float
    {
        return (float) str_replace(',', '', $amount);
    }

    private function parseDate(string $year, string $month, string $day, string $time): Carbon
    {
        return Carbon::createFromFormat(
            'Y-n-j H:i',
            "{$year}-{$month}-{$day} {$time}"
        );
    }
}
