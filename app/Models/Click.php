<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Click extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'counts',
    ];

    protected $casts = [
        'counts' => 'integer',
    ];

    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }
}