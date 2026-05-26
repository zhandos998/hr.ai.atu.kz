<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Application extends Model
{
    protected $guarded = [];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function scopeNotArchived($query)
    {
        return $query->whereNull('archived_at');
    }

    public function scopeArchived($query)
    {
        return $query->whereNotNull('archived_at');
    }

    public function stageLogs()
    {
        return $this->hasMany(ApplicationStageLog::class)->latest();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function archivedBy()
    {
        return $this->belongsTo(User::class, 'archived_by_user_id');
    }

    public function status()
    {
        return $this->belongsTo(ApplicationStatus::class);
    }

    public function vacancy()
    {
        return $this->belongsTo(Vacancy::class)->withTrashed();
    }

    public function resume() // одно резюме на заявку
    {
        return $this->hasOne(\App\Models\Resume::class);
    }

    public function documents()
    {
        return $this->hasMany(\App\Models\ApplicationDocument::class);
    }

    public function ppsProfile()
    {
        return $this->hasOne(ApplicationPpsProfile::class);
    }

    public function commissionVotes()
    {
        return $this->hasMany(ApplicationCommissionVote::class);
    }
}
