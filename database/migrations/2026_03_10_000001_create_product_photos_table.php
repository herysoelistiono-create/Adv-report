<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('image_path', 500);
            $table->string('caption', 255)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->datetime('created_datetime')->nullable();
            $table->foreignId('created_by_uid')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_photos');
    }
};
