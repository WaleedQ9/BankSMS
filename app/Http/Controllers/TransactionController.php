<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use App\Services\BillingCycleService;
use App\Services\BudgetService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TransactionController extends Controller
{
    public function __construct(
        private BillingCycleService $cycleService,
        private BudgetService $budgetService,
    ) {}

    public function index(Request $request)
    {
        $cycle = $this->cycleService->getCurrentCycle();
        $week = $this->cycleService->getCurrentWeek();

        $query = Transaction::with('category')->orderByDesc('transaction_date');

        $filter = $request->get('filter', 'all');

        switch ($filter) {
            case 'week':
                $query->where('week_id', $week->id);
                break;
            case 'month':
                $query->where('cycle_id', $cycle->id);
                break;
            case 'purchase':
                $query->where('type', 'purchase');
                break;
            case 'transfer':
                $query->where('type', 'transfer');
                break;
            case 'atm':
                $query->where('type', 'atm');
                break;
        }

        $transactions = $query->paginate(20);
        $categories = Category::where('is_active', true)->get();

        return view('transactions.index', compact('transactions', 'categories', 'filter'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        return view('transactions.create', compact('categories'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'merchant' => 'nullable|string|max:255',
            'type' => 'required|in:purchase,transfer,atm,income',
            'category_id' => 'nullable|exists:categories,id',
            'transaction_date' => 'required|date',
        ]);

        $date = Carbon::parse($request->transaction_date);
        $cycle = $this->cycleService->getCycleForDate($date);
        $week = $this->cycleService->getWeekForDate($cycle, $date);
        $isIncome = $request->type === 'income';
        $categoryId = $isIncome ? null : $request->category_id;

        $transaction = Transaction::create([
            'cycle_id' => $cycle->id,
            'week_id' => $week->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'merchant' => $request->merchant,
            'category_id' => $categoryId,
            'transaction_date' => $date,
            'is_classified' => $isIncome || (bool) $categoryId,
            'classified_at' => ($isIncome || $categoryId) ? now() : null,
            'needs_reminder' => !$categoryId && in_array($request->type, ['purchase', 'transfer', 'atm']),
            'sms_raw' => 'إدخال يدوي',
        ]);

        if ($transaction->is_classified && !$isIncome) {
            $this->budgetService->checkAndAlert($transaction->load(['category', 'cycle', 'week']));
        }

        return redirect()->route('transactions.index')->with('success', 'تم إضافة العملية بنجاح');
    }

    public function edit(Transaction $transaction)
    {
        $categories = Category::where('is_active', true)->get();
        return view('transactions.edit', compact('transaction', 'categories'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'merchant' => 'nullable|string|max:255',
            'type' => 'required|in:purchase,transfer,atm,income',
            'category_id' => 'nullable|exists:categories,id',
            'transaction_date' => 'required|date',
        ]);

        $date = Carbon::parse($request->transaction_date);
        $cycle = $this->cycleService->getCycleForDate($date);
        $week = $this->cycleService->getWeekForDate($cycle, $date);
        $isIncome = $request->type === 'income';
        $categoryId = $isIncome ? null : $request->category_id;

        $transaction->update([
            'cycle_id' => $cycle->id,
            'week_id' => $week->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'merchant' => $request->merchant,
            'category_id' => $categoryId,
            'transaction_date' => $date,
            'is_classified' => $isIncome || (bool) $categoryId,
            'classified_at' => ($isIncome || $categoryId) ? now() : null,
            'needs_reminder' => !$categoryId && in_array($request->type, ['purchase', 'transfer', 'atm']),
        ]);

        return redirect()->route('transactions.index')->with('success', 'تم تحديث العملية بنجاح');
    }

    public function destroy(Transaction $transaction)
    {
        $transaction->delete();
        return redirect()->route('transactions.index')->with('success', 'تم حذف العملية');
    }

    public function classify(Request $request, Transaction $transaction)
    {
        $request->validate(['category_id' => 'required|exists:categories,id']);

        $transaction->update([
            'category_id' => $request->category_id,
            'is_classified' => true,
            'classified_at' => now(),
            'needs_reminder' => false,
        ]);

        // Update Telegram message
        $fresh = $transaction->fresh(['category', 'cycle', 'week']);
        try {
            app(\App\Services\TelegramService::class)->updateClassifiedMessage($fresh);
        } catch (\Exception $e) {
            // Telegram update is non-critical
        }

        // Check budget alerts
        app(BudgetService::class)->checkAndAlert($fresh);

        return back()->with('success', 'تم التصنيف بنجاح');
    }
}
