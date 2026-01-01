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
        'quantity',
        'special_price',
        'week',
        'year',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'special_price' => 'float',
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