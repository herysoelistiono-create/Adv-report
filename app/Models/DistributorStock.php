<?php

namespace App\Models;

class DistributorStock extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'distributor_id',
        'product_id',
        'stock_quantity',
    ];

    protected $casts = [
        'distributor_id' => 'integer',
        'product_id'     => 'integer',
        'stock_quantity' => 'decimal:2',
    ];

    public function distributor()
    {
        return $this->belongsTo(Customer::class, 'distributor_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
