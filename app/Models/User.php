<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    public const LEGAL_ROLE_ALIASES = ['lawyer', 'compliance_director'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'is_commission_member',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    public function isAdmin()
    {
        return $this->role === 'admin';
    }

    public function isLawyer()
    {
        return in_array($this->role, self::LEGAL_ROLE_ALIASES, true);
    }

    public function isScienceDirector()
    {
        return $this->role === 'science_director';
    }

    public function isDigitalDirector()
    {
        return $this->role === 'digital_director';
    }

    public function isStrategyDirector()
    {
        return $this->role === 'strategy_director';
    }

    public function isAcademicDirector()
    {
        return $this->role === 'academic_director';
    }

    public function isLibraryDirector()
    {
        return $this->role === 'library_director';
    }

    public function isComplianceDirector()
    {
        return $this->isLawyer();
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class)->withTimestamps();
    }

    public function commissionMember()
    {
        return $this->hasOne(CommissionMember::class);
    }

    public function ppsFacultyCommissionMemberships()
    {
        return $this->hasMany(PpsFacultyCommissionMember::class);
    }

    public function commissionVotes()
    {
        return $this->hasMany(ApplicationCommissionVote::class);
    }

    public function commissionVacancies()
    {
        return $this->belongsToMany(Vacancy::class, 'vacancy_commission_member')->withTimestamps();
    }

    public function getIsCommissionMemberAttribute(): bool
    {
        if ($this->relationLoaded('commissionVacancies') && $this->commissionVacancies->isNotEmpty()) {
            return true;
        }

        if ($this->relationLoaded('commissionMember') && $this->commissionMember !== null && $this->commissionMember->isActive()) {
            return true;
        }

        if ($this->relationLoaded('ppsFacultyCommissionMemberships') && $this->ppsFacultyCommissionMemberships->isNotEmpty()) {
            return true;
        }

        if (Vacancy::query()
            ->whereHas('commissionMembers', function ($query) {
                $query->where('users.id', $this->id);
            })
            ->exists()) {
            return true;
        }

        return CommissionMember::query()
            ->where('user_id', $this->id)
            ->active()
            ->exists()
            || PpsFacultyCommissionMember::query()
                ->where('user_id', $this->id)
                ->exists();
    }
}
