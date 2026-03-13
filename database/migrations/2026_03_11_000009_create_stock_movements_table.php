<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
            $table->enum('type', ['in', 'out']);
            $table->decimal('quantity', 10, 2);
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->datetime('created_datetime')->nullable();
            $table->foreignId('created_by_uid')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['distributor_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
