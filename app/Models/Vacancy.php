<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vacancy extends Model
{
    use HasFactory;
    use SoftDeletes;

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
