<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelRequestDocument extends Model
{
    protected $guarded = [];

    public function personnelRequest()
    {
        return $this->belongsTo(PersonnelRequest::class);
    }
}
