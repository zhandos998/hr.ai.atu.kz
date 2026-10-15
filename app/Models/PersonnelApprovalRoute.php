<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelApprovalRoute extends Model
{
    protected $guarded = [];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
