<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Discount extends Model
{
    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'target_type',
        'user_id',
        'group_id',
        'menu_id',
        'min_order_total',
        'valid_from',
        'valid_until',
        'usage_limit',
        'usage_limit_per_user',
        'active',
    ];

    protected $casts = [
        'value' => 'float',
        'min_order_total' => 'float',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function redemptions()
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function isWithinDateWindow(?Carbon $at = null): bool
    {
        $at = $at ?? now();

        if ($this->valid_from && $at->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $at->gt($this->valid_until)) {
            return false;
        }

        return true;
    }

    public function hasRemainingGlobalUses(): bool
    {
        if ($this->usage_limit === null) {
            return true;
        }

        return $this->redemptions()->count() < $this->usage_limit;
    }

    public function hasRemainingUsesForUser(int $userId): bool
    {
        if ($this->usage_limit_per_user === null) {
            return true;
        }

        return $this->redemptions()->where('user_id', $userId)->count() < $this->usage_limit_per_user;
    }
}
