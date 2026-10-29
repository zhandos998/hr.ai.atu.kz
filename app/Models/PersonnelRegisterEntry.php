<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PersonnelRegisterEntry extends Model
{
    use SoftDeletes;

    public const TYPES = ['sick_leave', 'leave'];

    protected $fillable = [
        'employee_name', 'position_name', 'document_number',
        'starts_on', 'ends_on', 'diagnosis', 'leave_type',
    ];

    protected $hidden = ['created_by', 'updated_by', 'deleted_at'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'diagnosis' => 'encrypted',
        ];
    }
}
