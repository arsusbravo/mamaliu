<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'weekmenu_id',
        'user_id',
        'group_id',
        'pickup_point_id',
        'quantity',
        'special_price',
        'discount_id',
        'discount_amount',
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

    public function unitPrice(): float
    {
        return $this->special_price ?? $this->weekmenu->menu->price;
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