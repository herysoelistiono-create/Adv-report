<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistributorTarget extends Model
{
    protected $table = 'distributor_targets';

    public $timestamps = false;

    protected $fillable = [
        'distributor_id',
        'product_id',
        'fiscal_year',
        'month',
        'target_qty',
        'notes',
        'created_by_uid',
        'updated_by_uid',
        'created_datetime',
        'updated_datetime',
    ];

    protected $casts = [
        'distributor_id' => 'integer',
        'product_id'     => 'integer',
        'fiscal_year'    => 'integer',
        'month'          => 'integer',
        'target_qty'     => 'decimal:2',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function distributor()
    {
        return $this->belongsTo(Customer::class, 'distributor_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
