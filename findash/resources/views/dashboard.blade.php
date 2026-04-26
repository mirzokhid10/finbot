{{-- @extends('layouts.master')
@section('content')





    <section class="section">
        <div class="row">
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card card-statistic-2">
                    <div class="card-stats">
                        <div class="card-stats-title">Order Statistics -
                        </div>
                        <div class="card-stats-items">

                        </div>
                    </div>
                    <div class="card-icon shadow-primary bg-primary">
                        <i class="fas fa-archive"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Total Income</h4>
                        </div>
                        <div class="card-body">
                            {{ number_format($thisMonthIncome, 0) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card card-statistic-2">
                    <div class="card-chart">
                        <canvas id="balance-chart" height="80"></canvas>
                    </div>
                    <div class="card-icon shadow-primary bg-primary">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Total Expenses</h4>
                        </div>
                        <div class="card-body">
                            {{ number_format($thisMonthExpense, 0) }}
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card card-statistic-2">
                    <div class="card-chart">
                        <canvas id="sales-chart" height="80"></canvas>
                    </div>
                    <div class="card-icon shadow-primary bg-primary">
                        <i class="fas fa-shopping-bag"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Net Balance</h4>
                        </div>
                        <div class="card-body">
                            <span
                                class="{{ $thisMonthNet >= 0 ? 'text-green-600' : 'text-red-600' }}">{{ number_format($thisMonthNet, 0) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h4>Transactions</h4>
                        <div class="card-header-action">
                            <a href="{{ route('transactions.index') }}" class="btn btn-primary">View More <i
                                    class="fas fa-chevron-right"></i></a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive table-invoice">
                            @if ($recentTransactions->count() > 0)
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Category</th>
                                            <th>Note</th>
                                            <th>Type</th>
                                            <th>Date</th>
                                            <th class="text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($recentTransactions as $transaction)
                                            <tr>
                                                <!-- Transaction ID -->
                                                <td><a href="#">{{ $transaction->id }}</a></td>

                                                <!-- Category Name -->
                                                <td class="font-weight-600">
                                                    {{ $transaction->category->name ?? 'Uncategorized' }}
                                                </td>

                                                <!-- Note / Description -->
                                                <td class="text-muted text-small">
                                                    {{ Str::limit($transaction->note ?? 'No description', 20) }}
                                                </td>

                                                <!-- Status Badge (Income/Expense) -->
                                                <td>
                                                    @if ($transaction->type === 'income')
                                                        <div class="badge badge-success">Income</div>
                                                    @else
                                                        <div class="badge badge-danger">Expense</div>
                                                    @endif
                                                </td>

                                                <!-- Transaction Date -->
                                                <td>{{ $transaction->date->format('M d, Y') }}</td>

                                                <!-- Amount (Color Coded) -->
                                                <td
                                                    class="text-right font-weight-bold {{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }}">
                                                    {{ $transaction->type === 'income' ? '+' : '-' }}
                                                    {{ number_format($transaction->amount, 0) }}
                                                </td>

                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <!-- Empty State -->
                                <div class="px-6 py-12 text-center">
                                    <svg class="w-16 h-16 mx-auto text-gray-300 mb-4" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No transactions yet</h3>
                                    <p class="text-gray-500 mb-4">Start tracking your finances by adding your first
                                        transaction</p>
                                    <button onclick="openQuickAdd()"
                                        class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">
                                        Add Transaction
                                    </button>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </section>
@endsection --}}

@extends('layouts.master')

@section('content')
    <section class="section">
        <!-- Row 1: High-Level Stats -->
        <div class="row">
            <!-- Income Card -->
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card card-statistic-2">
                    <div class="card-stats">
                        <div class="card-stats-title text-success font-weight-bold">
                            <i class="fas fa-caret-up"></i> {{ number_format($incomeChange, 1) }}% vs last month
                        </div>
                    </div>
                    <div class="card-icon shadow-success bg-success">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Total Income</h4>
                        </div>
                        <div class="card-body">
                            <small class="text-muted" style="font-size: 12px">This Month</small><br>
                            {{ number_format($thisMonthIncome, 0) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expense Card -->
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card card-statistic-2">
                    <div class="card-stats">
                        <div class="card-stats-title text-danger font-weight-bold">
                            <i class="fas fa-caret-{{ $expenseChange >= 0 ? 'up' : 'down' }}"></i>
                            {{ number_format(abs($expenseChange), 1) }}%
                        </div>
                    </div>
                    <div class="card-icon shadow-danger bg-danger">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Total Expenses</h4>
                        </div>
                        <div class="card-body">
                            <small class="text-muted" style="font-size: 12px">This Month</small><br>
                            {{ number_format($thisMonthExpense, 0) }}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Net Balance Card -->
            <div class="col-lg-4 col-md-4 col-sm-12">
                <div class="card card-statistic-2">
                    <div class="card-stats">
                        <div class="card-stats-title text-primary font-weight-bold">
                            Overview
                        </div>
                    </div>
                    <div class="card-icon shadow-primary bg-primary">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <div class="card-wrap">
                        <div class="card-header">
                            <h4>Net Balance</h4>
                        </div>
                        <div class="card-body">
                            <small class="text-muted" style="font-size: 12px">Cash Flow</small><br>
                            <span class="{{ $thisMonthNet >= 0 ? 'text-success' : 'text-danger' }}">
                                {{ number_format($thisMonthNet, 0) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Charts (The Visual Heart) -->
        <div class="row">
            <!-- Bar Chart -->
            <div class="col-lg-8 col-md-12">
                <div class="card" style="min-height: 450px;"> <!-- Set a minimum height for the card -->
                    <div class="card-header">
                        <h4>Income vs Expense Trend</h4>
                    </div>
                    <div class="card-body">
                        <!-- We wrap the canvas in a div with a fixed height -->
                        <div style="height: 330px;">
                            <canvas id="balanceChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expense Distribution (Pie Chart) -->
            <div class="col-lg-4 col-md-12">
                <div class="card" style="min-height: 450px;"> <!-- Same height as the left card -->
                    <div class="card-header">
                        <h4>Expense Distribution</h4>
                    </div>
                    <div class="card-body">
                        <div style="height: 330px;">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Details -->
        <div class="row">
            <!-- Recent Transactions -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4>Recent Transactions</h4>
                        <div class="card-header-action">
                            <a href="{{ route('transactions.index') }}" class="btn btn-primary">View All</a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped mb-0">
                                <thead>
                                    <tr>
                                        <th>Category</th>
                                        <th>Type</th>
                                        <th>Date</th>
                                        <th class="text-right">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentTransactions as $transaction)
                                        <tr>
                                            <td>
                                                <div class="font-weight-600">
                                                    {{ $transaction->category->name ?? 'Uncategorized' }}</div>
                                                <div class="text-muted text-small">{{ Str::limit($transaction->note, 20) }}
                                                </div>
                                            </td>
                                            <td>
                                                <div
                                                    class="badge {{ $transaction->type == 'income' ? 'badge-success' : 'badge-danger' }}">
                                                    {{ ucfirst($transaction->type) }}
                                                </div>
                                            </td>
                                            <td>{{ $transaction->date->format('M d') }}</td>
                                            <td
                                                class="text-right font-weight-bold {{ $transaction->type === 'income' ? 'text-success' : 'text-danger' }}">
                                                {{ $transaction->type === 'income' ? '+' : '-' }}
                                                {{ number_format($transaction->amount, 0) }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center p-4 text-muted">No transactions recorded
                                                yet.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Categories List -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h4>Top Spending</h4>
                    </div>
                    <div class="card-body">
                        @foreach ($topCategories as $item)
                            @php
                                $max = $topCategories->first()->total;
                                $percent = ($item->total / $max) * 100;
                            @endphp
                            <div class="mb-4">
                                <div class="text-small float-right font-weight-bold text-muted">
                                    {{ number_format($item->total, 0) }}</div>
                                <div class="font-weight-bold">{{ $item->category->name ?? 'Other' }}</div>
                                <div class="progress" data-height="4">
                                    <div class="progress-bar bg-primary" role="progressbar"
                                        data-width="{{ $percent }}%" aria-valuenow="{{ $percent }}"
                                        aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // 1. Reverted to the Bar Chart Style (Income vs Expense)
        const ctxBalance = document.getElementById('balanceChart').getContext('2d');
        new Chart(ctxBalance, {
            type: 'bar', // Changed back to Bar
            data: {
                labels: {!! json_encode($months) !!},
                datasets: [{
                        label: 'Income',
                        data: {!! json_encode($incomeData) !!},
                        backgroundColor: '#47c363', // Solid Stisla Green
                        borderRadius: 5, // Slightly rounded corners for a modern look
                    },
                    {
                        label: 'Expense',
                        data: {!! json_encode($expenseData) !!},
                        backgroundColor: '#fc544b', // Solid Stisla Red
                        borderRadius: 5,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, //
                plugins: {
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            boxWidth: 6
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f9f9f9'
                        },
                        ticks: {
                            callback: function(value) {
                                return value.toLocaleString();
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // 2. Keep the Doughnut Chart for Categories
        const ctxCategory = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expenseByCategory->map(fn($item) => $item->category->name ?? 'Other')) !!},
                datasets: [{
                    data: {!! json_encode($expenseByCategory->pluck('total')) !!},
                    backgroundColor: ['#6777ef', '#3abaf4', '#ffa426', '#fc544b', '#47c363'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,

                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    }
                },
                cutout: '75%'
            }
        });
    </script>
@endpush
