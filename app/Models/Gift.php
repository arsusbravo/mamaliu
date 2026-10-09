<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Gift extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'target_type',
        'user_id',
        'group_id',
        'threshold_amount',
        'reward_menu_id',
        'valid_from',
        'valid_until',
        'active',
    ];

    protected $casts = [
        'threshold_amount' => 'float',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
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

    public function rewardMenu()
    {
        return $this->belongsTo(Menu::class, 'reward_menu_id');
    }

    public function qualifyingItems()
    {
        return $this->belongsToMany(Menu::class, 'gift_qualifying_items', 'gift_id', 'menu_id');
    }

    public function redemptions()
    {
        return $this->hasMany(GiftRedemption::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeCurrentlyValid($query)
    {
        $now = now();

        return $query->active()
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_from')->orWhere('valid_from', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            });
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
}
