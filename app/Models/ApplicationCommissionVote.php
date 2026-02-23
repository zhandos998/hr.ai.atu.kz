<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationCommissionVote extends Model
{
    protected $guarded = [];

    public function application()
    {
        return $this->belongsTo(Application::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

