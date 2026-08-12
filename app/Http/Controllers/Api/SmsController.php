<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use App\Services\SmsParserService;
use App\Services\TelegramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SmsController extends Controller
{
    public function __construct(
        private SmsParserService $parser,
        private BillingCycleService $cycleService,
        private TelegramService $telegram,
    ) {}

    public function receive(Request $request): JsonResponse
    {
        $request->validate(['message' => 'required|string']);

        $message = $request->input('message');
        $parsed = $this->parser->parse($message);

        if (!$parsed) {
            Log::info('SMS ignored (unknown type)', ['message' => mb_substr($message, 0, 100)]);
            return response()->json(['status' => 'ignored']);
        }

        $transactionDate = $parsed['transaction_date'] ?? now();
        // Use Unicode code points and the raw SMS as well as the parsed merchant. This
        // keeps salary-cycle detection reliable even when an older cached SMS pattern
        // does not contain the merchant name.
        $isSalary = $parsed['type'] === 'income'
            && preg_match('/\x{0631}\x{0627}\x{062A}\x{0628}/u', $message . ' ' . ($parsed['merchant'] ?? ''));

        // A salary transaction always becomes the first transaction in a fresh cycle.
        $cycle = $isSalary
            ? $this->cycleService->startCycleOnSalary($transactionDate)
            : $this->cycleService->getCycleForDate($transactionDate);
        $week = $this->cycleService->getWeekForDate($cycle, $transactionDate);

        $transaction = Transaction::create([
            'cycle_id' => $cycle->id,
            'week_id' => $week->id,
            'type' => $parsed['type'],
            'amount' => $parsed['amount'],
            'merchant' => $parsed['merchant'],
            'card_last4' => $parsed['card_last4'],
            'payment_method' => $parsed['payment_method'],
            'transaction_date' => $transactionDate,
            'sms_raw' => $message,
            'is_classified' => false,
            'needs_reminder' => in_array($parsed['type'], ['purchase', 'transfer', 'atm']),
        ]);

        if (in_array($parsed['type'], ['purchase', 'transfer', 'atm'])) {
            $this->telegram->sendClassificationMessage($transaction);
        }

        return response()->json([
            'status' => 'ok',
            'amount' => $transaction->amount,
            'transaction_id' => $transaction->id,
        ]);
    }
}
