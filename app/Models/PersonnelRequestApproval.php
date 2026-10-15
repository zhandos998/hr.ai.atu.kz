<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelRequestApproval extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
        ];
    }

    public function personnelRequest()
    {
        return $this->belongsTo(PersonnelRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
