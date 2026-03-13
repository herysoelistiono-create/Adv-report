<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('province_id')->nullable()->after('active')->constrained('provinces')->nullOnDelete();
            $table->foreignId('district_id')->nullable()->after('province_id')->constrained('districts')->nullOnDelete();
            $table->foreignId('village_id')->nullable()->after('district_id')->constrained('villages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropForeign(['village_id']);
            $table->dropForeign(['district_id']);
            $table->dropForeign(['province_id']);
            $table->dropColumn(['village_id', 'district_id', 'province_id']);
        });
    }
};
