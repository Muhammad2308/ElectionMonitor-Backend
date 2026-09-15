<?php

namespace App\Modules\ReferenceData\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $fillable = ['name', 'iso_code'];

    public function lgas()
    {
        return $this->hasMany(Lga::class);
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class);
    }
}
