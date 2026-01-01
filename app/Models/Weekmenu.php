<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Weekmenu extends Model
{
    use HasFactory;

    protected $table = 'weekmenu';

    protected $fillable = [
        'week',
        'year',
        'group_id',
        'menu_id',
        'quantity',
        'ordering',
    ];

    protected $casts = [
        'week' => 'integer',
        'year' => 'integer',
        'quantity' => 'integer',
        'ordering' => 'integer',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function scopeByWeek($query, $week, $year)
    {
        return $query->where('week', $week)->where('year', $year);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('ordering');
    }
}