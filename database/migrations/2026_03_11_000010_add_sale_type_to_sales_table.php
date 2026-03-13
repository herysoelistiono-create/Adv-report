<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // sale_type: 'distributor' = distributor→retailer (input by agronomist/distributor)
            //            'retailer'    = R1/R2→customer (input by BS)
            $table->string('sale_type', 20)->default('distributor')->after('id');
        });

        // Make distributor_id nullable (not required for retailer-type sales)
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('distributor_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('sale_type');
            $table->foreignId('distributor_id')->nullable(false)->change();
        });
    }
};
