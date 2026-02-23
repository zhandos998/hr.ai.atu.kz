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

    public function resume() // одно резюме на заявку
    {
        return $this->hasOne(\App\Models\Resume::class);
    }

    public function documents()
    {
        return $this->hasMany(\App\Models\ApplicationDocument::class);
    }

    public function commissionVotes()
    {
        return $this->hasMany(ApplicationCommissionVote::class);
    }
}
