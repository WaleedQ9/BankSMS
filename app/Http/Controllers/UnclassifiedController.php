<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;

class UnclassifiedController extends Controller
{
    public function index()
    {
        $transactions = Transaction::where('is_classified', false)
            ->whereIn('type', ['purchase', 'transfer', 'atm'])
            ->orderByDesc('transaction_date')
            ->get();

        $categories = Category::where('is_active', true)->get();

        return view('unclassified.index', compact('transactions', 'categories'));
    }
}
