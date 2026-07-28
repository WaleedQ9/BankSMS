<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CycleSnapshot;
use App\Services\BillingCycleService;
use App\Services\SmsParserService;
use App\Services\TelegramService;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
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

        // Auto-archive previous cycle when salary arrives
        if ($parsed['type'] === 'income' && $parsed['merchant'] === 'راتب') {
            $this->archivePreviousCycles();
        }

        $cycle = $this->cycleService->getCycleForDate($transactionDate);
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

        // Send Telegram classification message for expense types
        if (in_array($parsed['type'], ['purchase', 'transfer', 'atm'])) {
            $this->telegram->sendClassificationMessage($transaction);
        }

        return response()->json([
            'status' => 'ok',
            'amount' => $transaction->amount,
            'transaction_id' => $transaction->id,
        ]);
    }

    private function archivePreviousCycles(): void
    {
        try {
            Artisan::call('cycle:archive');
        } catch (\Exception $e) {
            Log::warning('Auto-archive failed', ['error' => $e->getMessage()]);
        }
    }
}
