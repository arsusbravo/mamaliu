<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Label extends Model
{
    protected $fillable = ['name', 'text', 'show_logo', 'text_color'];

    protected $casts = [
        'show_logo' => 'boolean',
    ];

    protected $appends = ['background_url', 'has_background'];

    public function backgroundPath(): string
    {
        return "labels/{$this->id}.jpg";
    }

    protected function hasBackground(): Attribute
    {
        return Attribute::make(
            get: fn () => Storage::disk('public')->exists($this->backgroundPath())
        );
    }

    protected function backgroundUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (! $this->has_background) {
                    return null;
                }

                $timestamp = Storage::disk('public')->lastModified($this->backgroundPath());

                return "/images/label-{$this->id}.jpg?v={$timestamp}";
            }
        );
    }

    public function getBackgroundFilePath(): ?string
    {
        return $this->has_background ? storage_path('app/public/' . $this->backgroundPath()) : null;
    }
}
