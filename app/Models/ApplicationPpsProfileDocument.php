<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationPpsProfileDocument extends Model
{
    protected $guarded = [];

    public function profile()
    {
        return $this->belongsTo(ApplicationPpsProfile::class, 'application_pps_profile_id');
    }
}
