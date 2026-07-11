<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasApiTokens, HasFactory, HasRoles, HasUuids, Notifiable, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_secret' => 'encrypted',
            'two_factor_recovery_codes' => 'encrypted:array',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Whether this user is a platform-level Super Admin (owned by no tenant).
     */
    public function isSuperAdmin(): bool
    {
        return $this->tenant_id === null;
    }

    /**
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Overrides Notifiable::notifications() (via HasDatabaseNotifications),
     * which hardcodes Laravel's stock Illuminate\Notifications\DatabaseNotification
     * — ours needs to be App\Models\Notification so readNotifications()/
     * unreadNotifications() (both built on top of this) resolve the UUID-keyed
     * subclass. Same underlying `notifications` table either way.
     *
     * @return MorphMany<Notification, $this>
     */
    public function notifications(): MorphMany
    {
        return $this->morphMany(Notification::class, 'notifiable')->latest();
    }

    /**
     * The ticket types this agent is qualified to pull from the pool (see
     * TicketPoolService) — an empty relation means "not qualified for
     * anything yet", not "qualified for everything": the pool fails closed,
     * same as every RLS-protected table in this app.
     *
     * @return BelongsToMany<TicketType, $this>
     */
    public function qualifiedTicketTypes(): BelongsToMany
    {
        return $this->belongsToMany(TicketType::class, 'agent_skills')->withTimestamps();
    }

    /**
     * The private broadcast channel TicketAssignedNotification et al.
     * broadcast on (see routes/channels.php) — tenant- and user-scoped,
     * rather than Laravel's default "App.Models.User.{id}" pattern which
     * assumes auto-increment integer keys.
     */
    public function receivesBroadcastNotificationsOn(): string
    {
        return "tenant.{$this->tenant_id}.user.{$this->id}";
    }
}
