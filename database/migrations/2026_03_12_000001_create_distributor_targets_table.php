<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('distributor_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distributor_id')->constrained('customers')->restrictOnDelete();
            $table->smallInteger('fiscal_year');
            $table->tinyInteger('month'); // 1–12
            $table->decimal('target_amount', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by_uid')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by_uid')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('created_datetime')->nullable();
            $table->datetime('updated_datetime')->nullable();

            $table->unique(['distributor_id', 'fiscal_year', 'month'], 'unique_distributor_target');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('distributor_targets');
    }
};
