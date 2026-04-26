<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function dashboard(Request $request)
    {

        $thisMonthIncome = Transaction::thisMonth()
            ->income()
            ->sum('amount');

        $thisMonthExpense = Transaction::thisMonth()
            ->expense()
            ->sum('amount');

        $thisMonthNet = $thisMonthIncome - $thisMonthExpense;

        // LAST MONTH
        $lastMonthIncome = Transaction::lastMonth()
            ->income()
            ->sum('amount');

        $lastMonthExpense = Transaction::lastMonth()
            ->expense()
            ->sum('amount');

        $lastMonthNet = $lastMonthIncome - $lastMonthExpense;

        // CALCULATE % CHANGE
        $incomeChange = $lastMonthIncome > 0
            ? (($thisMonthIncome - $lastMonthIncome) / $lastMonthIncome) * 100
            : 0;

        $expenseChange = $lastMonthExpense > 0
            ? (($thisMonthExpense - $lastMonthExpense) / $lastMonthExpense) * 100
            : 0;

        $netChange = $lastMonthNet != 0
            ? (($thisMonthNet - $lastMonthNet) / abs($lastMonthNet)) * 100
            : 0;

        // RECENT TRANSACTIONS
        $recentTransactions = Transaction::with('category')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // CATEGORIES FOR QUICK ADD FORM
        $categories = Category::orderBy('type')
            ->orderBy('name')
            ->get()
            ->groupBy('type');


        // 1. Pie Chart: Expenses by Category
        $expenseByCategory = Transaction::where('type', 'expense')
            ->with('category')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->get();

        // 2. Bar Chart: Income vs Expense (Last 6 Months)
        $months = [];
        $incomeData = [];
        $expenseData = [];

        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $months[] = $month->format('M');

            $incomeData[] = Transaction::where('type', 'income')
                ->whereMonth('date', $month->month)
                ->whereYear('date', $month->year)
                ->sum('amount');

            $expenseData[] = Transaction::where('type', 'expense')
                ->whereMonth('date', $month->month)
                ->whereYear('date', $month->year)
                ->sum('amount');
        }

        // 3. Top Spending Categories List
        $topCategories = Transaction::where('type', 'expense')
            ->with('category')
            ->select('category_id', DB::raw('SUM(amount) as total'))
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'thisMonthIncome',
            'thisMonthExpense',
            'thisMonthNet',
            'incomeChange',
            'lastMonthIncome',
            'lastMonthExpense',
            'lastMonthNet',
            'expenseChange',
            'netChange',
            'recentTransactions',
            'categories',
            'expenseByCategory',
            'months',
            'incomeData',
            'expenseData',
            'topCategories'
        ));
    }

    public function quickAdd(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
            'type' => 'required|in:income,expense',
            'category_id' => 'nullable|exists:categories,id',
            'date' => 'required|date',
            'note' => 'nullable|string|max:255'
        ]);

        $validated['user_id'] = 1; // For demo

        $transaction = Transaction::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transaction added successfully!',
            'transaction' => $transaction->load('category')
        ]);
    }
}
