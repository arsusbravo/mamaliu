<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailContent extends Model
{
    use HasFactory;

    protected $table = 'email_content';

    protected $fillable = [
        'title',
        'subject',
        'slug',
        'content',
    ];

    public function scopeBySlug($query, $slug)
    {
        return $query->where('slug', $slug);
    }
}