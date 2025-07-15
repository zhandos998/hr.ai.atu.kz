<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded = [];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function status()
    {
        return $this->belongsTo(ApplicationStatus::class);
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class);
    }
}
