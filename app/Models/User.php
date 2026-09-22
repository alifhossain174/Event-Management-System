<?php

namespace App\Models;

use App\Models\Concerns\HasDocuments;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasDocuments, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'deactivated_at',
        'deactivated_by_user_id',
        'last_login_at',
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
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_by_user_id', 'assigned_at']);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)
            ->withPivot(['assigned_by_user_id', 'assigned_at']);
    }

    public function clientProfile(): HasOne
    {
        return $this->hasOne(Client::class);
    }

    public function vendorProfile(): HasOne
    {
        return $this->hasOne(Vendor::class);
    }

    public function staffProfile(): HasOne
    {
        return $this->hasOne(StaffProfile::class);
    }

    public function managedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'manager_user_id');
    }

    public function managedStaffAssignments(): HasMany
    {
        return $this->hasMany(StaffAssignment::class, 'responsible_manager_user_id');
    }

    public function taskAssignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    public function notificationRecipients(): HasMany
    {
        return $this->hasMany(NotificationRecipient::class);
    }

    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class, 'created_by_user_id');
    }

    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'deactivated_by_user_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(UserStatusHistory::class);
    }

    public function hasRole(string $role): bool
    {
        $this->loadMissing('roles');

        return $this->roles->contains('slug', $role);
    }

    public function isAdministrator(): bool
    {
        return $this->hasRole('administrator');
    }

    public function hasPermission(string $permission): bool
    {
        $this->loadMissing('roles.permissions');

        if ($this->roles->contains('slug', 'administrator')) {
            return true;
        }

        return $this->roles->contains(
            fn (Role $role) => $role->permissions->contains('slug', $permission),
        );
    }

    public function scopeSearch($query, ?string $search)
    {
        return $query->when($search, function ($query, $search) {
            $query->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        });
    }

    public function scopeWithAccountStatus($query, ?string $status)
    {
        return match ($status) {
            'active' => $query->where('is_active', true)->whereNull('deleted_at'),
            'inactive' => $query->where('is_active', false)->whereNull('deleted_at'),
            'archived' => $query->onlyTrashed(),
            default => $query,
        };
    }
}
