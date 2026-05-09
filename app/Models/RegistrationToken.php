<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RegistrationToken extends Model
{
    use HasFactory;

    private const DEFAULT_VALID_DAYS = 3;
    protected $fillable = [
        'token',
        'valid_at',
        'valid_for_days',
    ];

    protected $casts = [
        'valid_at' => 'date',
        'valid_for_days' => 'integer',
    ];

    public function validDays(): int
    {
        return $this->valid_for_days ?? self::DEFAULT_VALID_DAYS;
    }

    public function scopeValid($query)
    {
        return $query->where('valid_at', '<=', now())
            ->where('valid_at', '>=', now()->subDays($this->validDays()));
    }

    public function scopeByToken($query, $token)
    {
        return $query->where('token', $token);
    }

    public function isValid(): bool
    {
        // Valid if valid_at is in the past and not more than $this->validDays() days old
        return $this->valid_at <= now() && $this->valid_at >= now()->subDays($this->validDays());
    }

    public static function generate($validAt = null, ?int $validForDays = null): self
    {
        return self::create([
            'token' => Str::random(64),
            'valid_at' => $validAt ?? now(),
            'valid_for_days' => $validForDays,
        ]);
    }

    public function getExpiresAtAttribute()
    {
        return $this->valid_at->addDays($this->validDays());
    }
}