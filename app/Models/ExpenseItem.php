<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExpenseItem extends Model
{
    protected $fillable = ['expense_id', 'name', 'quantity', 'price', 'category'];

    protected $casts = [
        'quantity' => 'float',
        'price' => 'float',
    ];

    public function expense()
    {
        return $this->belongsTo(Expense::class);
    }
}
