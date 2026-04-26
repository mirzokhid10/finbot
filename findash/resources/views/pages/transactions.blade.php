@extends('layouts.master')
@section('content')
    <section class="section">
        <div class="section-header">
            <h1>Transactions</h1>>
        </div>

        <div class="section-body">
            <!-- Filter Card -->
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('transactions.index') }}" class="row">
                        <div class="col-md-2 font-group">
                            <label>Type</label>
                            <select name="type" class="form-control">
                                <option value="">All</option>
                                <option value="income" {{ request('type') == 'income' ? 'selected' : '' }}>Income</option>
                                <option value="expense" {{ request('type') == 'expense' ? 'selected' : '' }}>Expense
                                </option>
                            </select>
                        </div>
                        <div class="col-md-3 font-group">
                            <label>Category</label>
                            <select name="category_id" class="form-control">
                                <option value="">All Categories</option>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}"
                                        {{ request('category_id') == $cat->id ? 'selected' : '' }}>
                                        {{ $cat->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2 font-group">
                            <label>From</label>
                            <input type="date" name="from" class="form-control" value="{{ request('from') }}">
                        </div>
                        <div class="col-md-2 font-group">
                            <label>To</label>
                            <input type="date" name="to" class="form-control" value="{{ request('to') }}">
                        </div>
                        <div class="col-md-3 font-group">
                            <label>&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary flex-fill">Filter</button>
                                <a href="{{ route('transactions.index') }}" class="btn btn-secondary flex-fill">Reset</a>
                                <a href="{{ route('transactions.export', request()->all()) }}"
                                    class="btn btn-secondary flex-fill">Export XLS</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Table Card -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="table-1">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>User</th>
                                    <th>Phone</th>
                                    <th>Tg account</th> <!-- New Column -->
                                    <th>Category</th>
                                    <th>Note</th>
                                    <th>Type</th>
                                    <th class="text-right">Amount</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($transactions as $tx)
                                    <tr>
                                        <td>{{ $tx->date->format('d M, Y') }}</td>

                                        <!-- User Info Column -->
                                        <td>{{ $tx->user->name }}</td>
                                        <td>{{ $tx->user->phone ?? 'No Phone' }}</td>
                                        <td><a href="https://t.me/{{ $tx->user->username }}" target="_blank"
                                                class="text-primary">
                                                {{ '@' . ($tx->user->username ?? 'N/A') }}
                                            </a></td>
                                        <td class="font-weight-600">
                                            <span class="badge badge-light">{{ $tx->category->name ?? 'N/A' }}</span>
                                        </td>

                                        <td class="text-muted small">{{ Str::limit($tx->note, 20) }}</td>

                                        <td>
                                            <div
                                                class="badge {{ $tx->type == 'income' ? 'badge-success' : 'badge-danger' }}">
                                                {{ ucfirst($tx->type) }}
                                            </div>
                                        </td>

                                        <td
                                            class="text-right font-weight-bold {{ $tx->type == 'income' ? 'text-success' : 'text-danger' }}">
                                            {{ $tx->type == 'income' ? '+' : '-' }} {{ number_format($tx->amount, 0) }}
                                        </td>

                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-2">
                                                <button class="btn btn-primary edit-btn" data-id="{{ $tx->id }}"
                                                    data-amount="{{ $tx->amount }}" data-type="{{ $tx->type }}"
                                                    data-category="{{ $tx->category_id }}"
                                                    data-date="{{ $tx->date->format('Y-m-d') }}"
                                                    data-note="{{ $tx->note }}" title="Edit">
                                                    <i class="fas fa-pencil-alt"></i>
                                                </button>
                                                <form action="{{ route('transactions.destroy', $tx->id) }}" method="POST"
                                                    onsubmit="return confirm('Delete this?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>

                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="modal fade" id="editModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Transaction</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <form id="editForm" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Type</label>
                                    <select name="type" id="edit_type" class="form-control" required>
                                        <option value="income">Income</option>
                                        <option value="expense">Expense</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-group">
                                    <label>Amount</label>
                                    <input type="number" name="amount" id="edit_amount" class="form-control" required>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Category</label>
                            <select name="category_id" id="edit_category" class="form-control" required>
                                @foreach ($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Date</label>
                            <input type="date" name="date" id="edit_date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label>Note</label>
                            <input type="text" name="note" id="edit_note" class="form-control">
                        </div>
                    </div>
                    <div class="modal-footer bg-whitesmoke br">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('.edit-btn').on('click', function() {
                // 1. Get data from button attributes
                const id = $(this).data('id');
                const amount = $(this).data('amount');
                const type = $(this).data('type');
                const category = $(this).data('category');
                const date = $(this).data('date');
                const note = $(this).data('note');

                // 2. Set Form Action URL dynamically
                $('#editForm').attr('action', '/transactions/' + id);

                // 3. Fill Modal Fields
                $('#edit_amount').val(amount);
                $('#edit_type').val(type);
                $('#edit_category').val(category);
                $('#edit_date').val(date);
                $('#edit_note').val(note);

                // 4. Show Modal
                $('#editModal').modal('show');
            });
        });
    </script>
@endsection
