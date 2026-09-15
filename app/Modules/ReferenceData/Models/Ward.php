<?php

namespace App\Modules\ReferenceData\Models;

use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    protected $fillable = ['lga_id', 'name'];

    public function lga()
    {
        return $this->belongsTo(Lga::class);
    }

    public function pollingUnits()
    {
        return $this->hasMany(PollingUnit::class);
    }
}
