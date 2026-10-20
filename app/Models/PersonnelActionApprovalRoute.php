<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelActionApprovalRoute extends Model
{
    protected $guarded = [];

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
