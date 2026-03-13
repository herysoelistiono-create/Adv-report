<?php

namespace App\Models;

class District extends Model
{
    public $timestamps = true;

    protected $fillable = ['province_id', 'name'];

    protected $casts = [
        'province_id' => 'integer',
    ];

    public function province()
    {
        return $this->belongsTo(Province::class);
    }

    public function villages()
    {
        return $this->hasMany(Village::class);
    }
}
