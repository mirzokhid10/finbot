<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Category;
use App\Models\User;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $auth = auth();
        $query = Transaction::with(['category', 'user']);
        $users = User::get();

        // Apply Filters
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->to);
        }

        $transactions = $query->orderBy('date', 'desc')->get();

        $categories = Category::where('user_id', auth()->id())->get();


        return view('pages.transactions', compact('transactions', 'categories', 'users'));
    }

    public function update(Request $request, Transaction $transaction)
    {
        // 1. Validate
        $request->validate([
            'amount' => 'required|numeric',
            'type' => 'required|in:income,expense',
            'category_id' => 'required|exists:categories,id',
            'date' => 'required|date',
            'note' => 'nullable|string|max:255',
        ]);

        // 2. Update
        $transaction->update($request->all());

        return back()->with('success', 'Transaction updated successfully!');
    }

    public function destroy(Transaction $transaction)
    {
        if ($transaction->user_id !== auth()->id()) abort(403);
        $transaction->delete();
        return back()->with('success', 'Transaction deleted successfully');
    }

    public function export(Request $request)
    {
        $query = Transaction::with('category');

        // Re-apply same filters for export
        if ($request->filled('type')) $query->where('type', $request->type);
        if ($request->filled('from')) $query->whereDate('date', '>=', $request->from);
        if ($request->filled('to')) $query->whereDate('date', '<=', $request->to);

        $transactions = $query->get();

        $response = new StreamedResponse(function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['Date', 'Type', 'Category', 'Amount', 'Note']);

            foreach ($transactions as $tx) {
                fputcsv($handle, [
                    $tx->date->format('Y-m-d'),
                    ucfirst($tx->type),
                    $tx->category->name ?? 'N/A',
                    $tx->amount,
                    $tx->note
                ]);
            }
            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('Content-Disposition', 'attachment; filename="transactions_export.csv"');

        return $response;
    }
}
