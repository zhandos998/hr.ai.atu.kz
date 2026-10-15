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
    use HasApiTokens, HasFactory, Notifiable;

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
        'contact_info',
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

    protected $appends = [];

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

    public function isProfessionalDevelopmentDirector()
    {
        return $this->role === 'professional_development_director';
    }

    public function isEconomist(): bool
    {
        return $this->role === 'economist';
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

    public function personnelApprovals()
    {
        return $this->hasMany(PersonnelRequestApproval::class);
    }

    public function getHasPersonnelApprovalsAttribute(): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        if ($this->relationLoaded('personnelApprovals')) {
            return $this->personnelApprovals->isNotEmpty();
        }

        return PersonnelRequestApproval::query()->where('user_id', $this->id)->exists();
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

        if (! $this->relationLoaded('commissionVacancies') && $this->commissionVacancies()->exists()) {
            return true;
        }

        return (! $this->relationLoaded('commissionMember') && CommissionMember::query()
            ->where('user_id', $this->id)
            ->active()
            ->exists())
            || (! $this->relationLoaded('ppsFacultyCommissionMemberships') && PpsFacultyCommissionMember::query()
                ->where('user_id', $this->id)
                ->exists());
    }
}
