<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');
            $table->decimal('amount', 15, 2);
            $table->enum('type', ['income', 'expense']);
            $table->foreignId('category_id')
                ->nullable()
                ->constrained()
                ->onDelete('set null');
            $table->date('date'); // The actual date of the transaction
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']); // Drop foreign key first
        });
        Schema::dropIfExists('transactions');
    }
};
