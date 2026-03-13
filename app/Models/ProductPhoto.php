<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPhoto extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'product_id',
        'image_path',
        'caption',
        'sort_order',
        'created_datetime',
        'created_by_uid',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            $model->created_datetime = now();
            $model->created_by_uid   = auth()->id();
        });
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
