@extends('layouts.master')

@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Analytics</h1>
        </div>

        <div class="section-body">
            <div class="row">
                <!-- Bar Chart: Income vs Expense -->
                <div class="col-12 col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h4>Income vs Expense (Last 6 Months)</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="balanceChart" height="150"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Pie Chart: Expenses by Category -->
                <div class="col-12 col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h4>Expense Distribution</h4>
                        </div>
                        <div class="card-body">
                            <canvas id="categoryChart" height="310"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-2">
                    <div class="card">
                        <div class="card-header">
                            <h4>Top Spending Categories</h4>
                        </div>
                        <div class="card-body">
                            @foreach ($topCategories as $item)
                                <div class="mb-4">
                                    <div class="text-small float-right font-weight-bold text-muted">
                                        {{ number_format($item->total, 0) }}</div>
                                    <div class="font-weight-bold text-primary">
                                        {{ $item->category->name ?? 'Uncategorized' }}</div>
                                    <div class="progress" data-height="5">
                                        @php
                                            $max = $topCategories->first()->total;
                                            $percent = ($item->total / $max) * 100;
                                        @endphp
                                        <div class="progress-bar" role="progressbar" data-width="{{ $percent }}%"
                                            aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Top Spending Categories List -->

            </div>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // 1. Income vs Expense Bar Chart
        const ctxBalance = document.getElementById('balanceChart').getContext('2d');
        new Chart(ctxBalance, {
            type: 'bar',
            data: {
                labels: {!! json_encode($months) !!},
                datasets: [{
                        label: 'Income',
                        data: {!! json_encode($incomeData) !!},
                        backgroundColor: '#47c363',
                    },
                    {
                        label: 'Expense',
                        data: {!! json_encode($expenseData) !!},
                        backgroundColor: '#fc544b',
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 2. Expense Pie Chart
        const ctxCategory = document.getElementById('categoryChart').getContext('2d');
        new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode($expenseByCategory->map(fn($item) => $item->category->name ?? 'Other')) !!},
                datasets: [{
                    data: {!! json_encode($expenseByCategory->pluck('total')) !!},
                    backgroundColor: ['#6777ef', '#3abaf4', '#ffa426', '#fc544b', '#47c363', '#9c27b0'],
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
@endpush
