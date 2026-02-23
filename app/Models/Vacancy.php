<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vacancy extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function applications()
    {
        return $this->hasMany(Application::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function commissionMembers()
    {
        return $this->belongsToMany(User::class, 'vacancy_commission_member')->withTimestamps();
    }
}
