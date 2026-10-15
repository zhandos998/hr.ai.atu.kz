<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $guarded = [];

    public function academicUnit()
    {
        return $this->belongsTo(AcademicUnit::class);
    }

    public function parent()
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }

    public function positions()
    {
        return $this->hasMany(Position::class);
    }

    public function personnelApprovalRoutes()
    {
        return $this->hasMany(PersonnelApprovalRoute::class);
    }
}
