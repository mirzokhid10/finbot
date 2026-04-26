<?php

namespace App\Http\Controllers;


use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index()
    {


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

        return view('pages.analytics', compact(
            'expenseByCategory',
            'months',
            'incomeData',
            'expenseData',
            'topCategories'
        ));
    }
}
