<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Drop FK before dropping unique index (MySQL FK uses the index)
        DB::statement('ALTER TABLE distributor_targets DROP FOREIGN KEY distributor_targets_distributor_id_foreign');
        DB::statement('ALTER TABLE distributor_targets DROP INDEX unique_distributor_target');
        DB::statement('ALTER TABLE distributor_targets DROP COLUMN target_amount');

        // Add product_id + target_qty columns
        DB::statement('ALTER TABLE distributor_targets ADD COLUMN product_id BIGINT UNSIGNED NOT NULL AFTER distributor_id');
        DB::statement('ALTER TABLE distributor_targets ADD COLUMN target_qty DECIMAL(10,2) DEFAULT NULL AFTER `month`');

        // New unique index: distributor + product + fiscal_year + month
        DB::statement('ALTER TABLE distributor_targets ADD UNIQUE KEY unique_distributor_target (distributor_id, product_id, fiscal_year, `month`)');

        // Re-add FKs
        DB::statement('ALTER TABLE distributor_targets ADD CONSTRAINT distributor_targets_distributor_id_foreign FOREIGN KEY (distributor_id) REFERENCES customers(id) ON DELETE RESTRICT');
        DB::statement('ALTER TABLE distributor_targets ADD CONSTRAINT distributor_targets_product_id_foreign FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE distributor_targets DROP FOREIGN KEY distributor_targets_distributor_id_foreign');
        DB::statement('ALTER TABLE distributor_targets DROP FOREIGN KEY distributor_targets_product_id_foreign');
        DB::statement('ALTER TABLE distributor_targets DROP INDEX unique_distributor_target');
        DB::statement('ALTER TABLE distributor_targets DROP COLUMN product_id');
        DB::statement('ALTER TABLE distributor_targets DROP COLUMN target_qty');
        DB::statement('ALTER TABLE distributor_targets ADD COLUMN target_amount DECIMAL(15,2) DEFAULT NULL');
        DB::statement('ALTER TABLE distributor_targets ADD UNIQUE KEY unique_distributor_target (distributor_id, fiscal_year, `month`)');
        DB::statement('ALTER TABLE distributor_targets ADD CONSTRAINT distributor_targets_distributor_id_foreign FOREIGN KEY (distributor_id) REFERENCES customers(id) ON DELETE RESTRICT');
    }
};
