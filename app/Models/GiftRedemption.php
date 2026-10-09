<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftRedemption extends Model
{
    protected $fillable = [
        'gift_id',
        'user_id',
        'group_id',
        'week',
        'year',
        'qualifying_total',
        'gift_quantity',
    ];

    protected $casts = [
        'qualifying_total' => 'float',
        'gift_quantity' => 'integer',
    ];

    public function gift()
    {
        return $this->belongsTo(Gift::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
