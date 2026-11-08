<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['store', 'purchased_at', 'total', 'receipt_image', 'notes'];

    protected $casts = [
        'purchased_at' => 'date:Y-m-d',
        'total' => 'float',
    ];

    protected $appends = ['has_receipt', 'items_total'];

    public function items()
    {
        return $this->hasMany(ExpenseItem::class)->orderBy('id');
    }

    public function getHasReceiptAttribute(): bool
    {
        return $this->receipt_image !== null;
    }

    public function getItemsTotalAttribute(): float
    {
        return round((float) $this->items->sum('price'), 2);
    }
}
