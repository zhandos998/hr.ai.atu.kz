<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationPpsProfile extends Model
{
    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function documents()
    {
        return $this->hasMany(ApplicationPpsProfileDocument::class, 'application_pps_profile_id');
    }
}
