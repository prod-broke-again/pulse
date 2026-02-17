<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
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
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function assignedChats(): HasMany
    {
        return $this->hasMany(\App\Infrastructure\Persistence\Eloquent\ChatModel::class, 'assigned_to');
    }

    /** Moderator's departments (only those departments' chats appear in "Нераспределённые"). */
    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Infrastructure\Persistence\Eloquent\DepartmentModel::class,
            'department_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'department_id',
        );
    }

    /** Projects/sources available to moderator. */
    public function sources(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Infrastructure\Persistence\Eloquent\SourceModel::class,
            'source_user',
            foreignPivotKey: 'user_id',
            relatedPivotKey: 'source_id',
        );
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasAnyRole(['admin', 'moderator']);
    }
}
