<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'weekmenu_id',
        'menu_id',
        'user_id',
        'group_id',
        'pickup_point_id',
        'quantity',
        'special_price',
        'discount_id',
        'discount_amount',
        'gift_redemption_id',
        'week',
        'year',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'special_price' => 'float',
        'discount_amount' => 'float',
        'week' => 'integer',
        'year' => 'integer',
    ];

    protected $appends = ['menu_item', 'is_gift'];

    public function weekmenu()
    {
        return $this->belongsTo(Weekmenu::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function pickupPoint()
    {
        return $this->belongsTo(PickupPoint::class);
    }

    public function discount()
    {
        return $this->belongsTo(Discount::class);
    }

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function giftRedemption()
    {
        return $this->belongsTo(GiftRedemption::class);
    }

    protected function menuItem(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->weekmenu_id ? $this->weekmenu->menu : $this->menu
        );
    }

    protected function isGift(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->gift_redemption_id !== null
        );
    }

    public function unitPrice(): float
    {
        return $this->special_price ?? $this->menu_item->price;
    }

    public function lineSubtotal(): float
    {
        return $this->unitPrice() * $this->quantity;
    }

    public function lineDiscount(): float
    {
        return (float) ($this->discount_amount ?? 0);
    }

    public function lineTotal(): float
    {
        return $this->lineSubtotal() - $this->lineDiscount();
    }

    public function invoiceItems()
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function invoices()
    {
        return $this->belongsToMany(Invoice::class, 'invoice_items');
    }

    public function scopeByWeek($query, $week, $year)
    {
        return $query->where('week', $week)->where('year', $year);
    }

    public function anOrder($query, $week, $year, $userId)
    {
        return $query->where('week', $week)->where('year', $year)->where('user_id', $userId);
    }
}