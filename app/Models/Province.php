<?php

namespace App\Models;

class Province extends Model
{
    public $timestamps = true;

    protected $fillable = ['name'];

    public function districts()
    {
        return $this->hasMany(District::class);
    }
}
