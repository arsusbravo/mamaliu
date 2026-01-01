<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Menu extends Model
{
    use HasFactory;

    protected $table = 'menu';

    protected $fillable = [
        'label',
        'description',
        'price',
        'menutype',
    ];

    protected $casts = [
        'price' => 'float',
    ];

    protected $appends = ['image_url', 'has_image'];

    public function clicks()
    {
        return $this->hasMany(Click::class);
    }

    public function weekMenus()
    {
        return $this->hasMany(Weekmenu::class);
    }

    public function scopePublished($query)
    {
        return $query->where('published', 1);
    }

    public function scopeByMenutype($query, $type)
    {
        return $query->where('menutype', $type);
    }

    // Check if image exists
    protected function hasImage(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk('public')->exists("menus/{$this->id}.jpg")
        );
    }

    // Get image URL
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->has_image) {
                    return null;
                }

                $timestamp = Storage::disk('public')->lastModified("menus/{$this->id}.jpg");
                return "/images/menu-{$this->id}.jpg?v={$timestamp}";
            }
        );
    }

    // Get actual file path for serving
    public function getImagePath(): ?string
    {
        if ($this->has_image) {
            return storage_path("app/public/menus/{$this->id}.jpg");
        }
        return null;
    }
}