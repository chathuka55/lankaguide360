<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// `role` is deliberately not fillable: only admins change it, explicitly.
#[Fillable(['name', 'email', 'password', 'phone', 'country', 'age'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => 'traveller',
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
            'role' => UserRole::class,
            'age' => 'integer',
        ];
    }

    /**
     * Trips the user planned as a traveller.
     *
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Trips assigned to the user as the reviewing agent.
     *
     * @return HasMany<Trip, $this>
     */
    public function assignedTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'agent_id');
    }

    /**
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function hasRole(UserRole|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === ($role instanceof UserRole ? $role : UserRole::from($role))) {
                return true;
            }
        }

        return false;
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isAgent(): bool
    {
        return $this->role === UserRole::Agent;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * Where to send the user after login or registration.
     */
    public function homeRoute(): string
    {
        return $this->isStaff() ? route('admin.dashboard', absolute: false) : route('my-trips', absolute: false);
    }
}
