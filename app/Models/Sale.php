<?php

namespace App\Models;

class Sale extends Model
{
    public const Type_Distributor = 'distributor'; // Distributor → R1/R2 (input by agronomist)
    public const Type_Retailer    = 'retailer';    // R1/R2 → customer (input by BS)

    protected $fillable = [
        'sale_type',
        'date',
        'distributor_id',
        'retailer_id',
        'province_id',
        'district_id',
        'village_id',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'date'           => 'date',
        'distributor_id' => 'integer',
        'retailer_id'    => 'integer',
        'province_id'    => 'integer',
        'district_id'    => 'integer',
        'village_id'     => 'integer',
        'total_amount'   => 'decimal:2',
        'created_by_uid' => 'integer',
        'updated_by_uid' => 'integer',
    ];

    public function distributor()
    {
        return $this->belongsTo(Customer::class, 'distributor_id');
    }

    public function retailer()
    {
        return $this->belongsTo(Customer::class, 'retailer_id');
    }

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function district()
    {
        return $this->belongsTo(District::class);
    }

    public function village()
    {
        return $this->belongsTo(Village::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function created_by_user()
    {
        return $this->belongsTo(User::class, 'created_by_uid');
    }

    public function updated_by_user()
    {
        return $this->belongsTo(User::class, 'updated_by_uid');
    }
}
