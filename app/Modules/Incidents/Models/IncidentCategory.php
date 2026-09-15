<?php

namespace App\Modules\Incidents\Models;

use Illuminate\Database\Eloquent\Model;

class IncidentCategory extends Model
{
    protected $fillable = ['name', 'description'];

    public function incidents()
    {
        return $this->hasMany(Incident::class, 'category_id');
    }
}
