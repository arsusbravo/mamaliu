<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegistrationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'valid_at',
    ];

    protected $casts = [
        'valid_at' => 'date',
    ];

    public function scopeValid($query)
    {
        return $query->where('valid_at', '<=', now())
            ->where('valid_at', '>=', now()->subDays(3));
    }

    public function scopeByToken($query, $token)
    {
        return $query->where('token', $token);
    }

    public function isValid(): bool
    {
        // Valid if valid_at is in the past and not more than 3 days old
        return $this->valid_at <= now() && $this->valid_at >= now()->subDays(3);
    }

    public static function generate($validAt = null): self
    {
        return self::create([
            'token' => Str::random(64),
            'valid_at' => $validAt ?? now(),
        ]);
    }

    public function getExpiresAtAttribute()
    {
        return $this->valid_at->addDays(3);
    }
}