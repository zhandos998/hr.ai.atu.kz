<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonnelRequest extends Model
{
    public const ACTION_HIRE = 'hire';
    public const ACTION_DISMISSAL = 'dismissal';
    public const ACTION_TRANSFER = 'transfer';
    public const ACTION_MATERNITY_LEAVE = 'maternity_leave';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'salary' => 'decimal:2',
        ];
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    public function targetDepartment()
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function targetPosition()
    {
        return $this->belongsTo(Position::class, 'target_position_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function documents()
    {
        return $this->hasMany(PersonnelRequestDocument::class);
    }

    public function approvals()
    {
        return $this->hasMany(PersonnelRequestApproval::class)->orderBy('approval_order');
    }

    public function refreshStatus(): void
    {
        $decisions = $this->approvals()->pluck('decision');

        $status = match (true) {
            $decisions->contains('rejected') => 'rejected',
            $decisions->isNotEmpty() && $decisions->every(fn ($decision) => $decision === 'approved') => 'approved',
            default => 'pending',
        };

        if ($this->status !== $status) {
            $this->update(['status' => $status]);
        }
    }
}
