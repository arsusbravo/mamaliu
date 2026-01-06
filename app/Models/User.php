<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable implements CanResetPasswordContract
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable, CanResetPassword;

    protected $fillable = [
        'name',
        'username',
        'password',
        'phone',
        'client_token',
        'group_id',
        'usertype',
        'active',
        'display_menus',
        'cookielogin',
        'reregistered',
        'email_verified_at',
        'last_loggedin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'client_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected $appends = ['email'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'display_menus' => 'boolean',
            'cookielogin' => 'boolean',
            'reregistered' => 'boolean',
            'email_verified_at' => 'datetime',
            'last_loggedin' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // Accessor: Make username accessible as email
    public function getEmailAttribute()
    {
        return $this->username;
    }

    // Mutator: When setting email, set username instead
    public function setEmailAttribute($value)
    {
        $this->attributes['username'] = $value;
    }

    // Override for password resets
    public function getEmailForPasswordReset()
    {
        return $this->username;
    }

    // Override for email verification
    public function getEmailForVerification()
    {
        return $this->username;
    }

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \Illuminate\Auth\Notifications\ResetPassword($token));
    }

    // Relationships
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', 1);
    }

    public function scopeByUsertype($query, $type)
    {
        return $query->where('usertype', $type);
    }
}