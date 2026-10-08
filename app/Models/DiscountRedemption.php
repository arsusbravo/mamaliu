<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiscountRedemption extends Model
{
    protected $fillable = [
        'discount_id',
        'user_id',
        'group_id',
        'week',
        'year',
        'order_total_before',
        'discount_amount',
    ];

    protected $casts = [
        'order_total_before' => 'float',
        'discount_amount' => 'float',
    ];

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
