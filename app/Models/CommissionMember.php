<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommissionMember extends Model
{
    public const TYPE_PPS = 'pps';

    public const TYPE_STAFF = 'staff';

    public const TYPE_UVP = 'uvp';

    public const TYPES = [
        self::TYPE_PPS,
        self::TYPE_STAFF,
        self::TYPE_UVP,
    ];

    protected $guarded = [];

    protected $casts = [
        'is_pps' => 'boolean',
        'is_staff' => 'boolean',
        'is_uvp' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive($query)
    {
        return $query->where(function ($builder) {
            $builder->where('is_pps', true)
                ->orWhere('is_staff', true)->orWhere('is_uvp', true);
        });
    }

    public function scopeForVacancyType($query, ?string $type)
    {
        $column = self::flagColumnForType($type);

        if ($column === null) {
            return $query->active();
        }

        return $query->where($column, true);
    }

    public static function flagColumnForType(?string $type): ?string
    {
        return match ($type) {
            self::TYPE_PPS => 'is_pps',
            self::TYPE_STAFF => 'is_staff',
            self::TYPE_UVP => 'is_uvp',
            default => null,
        };
    }

    public function isActive(): bool
    {
        return (bool) $this->is_pps || (bool) $this->is_staff || (bool) $this->is_uvp;
    }
}
