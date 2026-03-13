<?php

namespace App\Models;

class SaleItem extends \Illuminate\Database\Eloquent\Model
{
    public $timestamps = false;

    protected $fillable = [
        'sale_id',
        'product_id',
        'quantity',
        'unit',
        'price',
        'subtotal',
    ];

    protected $casts = [
        'sale_id'    => 'integer',
        'product_id' => 'integer',
        'quantity'   => 'decimal:2',
        'price'      => 'decimal:2',
        'subtotal'   => 'decimal:2',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
