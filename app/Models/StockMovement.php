<?php

namespace App\Models;

class StockMovement extends Model
{
    protected $fillable = [
        'distributor_id',
        'product_id',
        'type',
        'quantity',
        'reference',
        'notes',
    ];

    protected $casts = [
        'distributor_id' => 'integer',
        'product_id'     => 'integer',
        'quantity'       => 'decimal:2',
        'created_by_uid' => 'integer',
    ];

    public function distributor()
    {
        return $this->belongsTo(Customer::class, 'distributor_id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by_uid');
    }
}
