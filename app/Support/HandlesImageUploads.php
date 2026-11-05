<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

trait HandlesImageUploads
{
    protected function isHeicUpload(UploadedFile $file): bool
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mime = strtolower((string) $file->getMimeType());

        return in_array($extension, ['heic', 'heif'], true) || str_contains($mime, 'hei');
    }

    /**
     * GD (the default driver) can't decode HEIC/HEIF at all. Imagick can, but
     * only when it was compiled with libheif support, which isn't guaranteed
     * on every server. Return a friendly error if we're about to fail.
     */
    protected function unsupportedImageError(UploadedFile $file): ?string
    {
        if (!$this->isHeicUpload($file)) {
            return null;
        }

        if ($this->imagickSupportsHeic()) {
            return null;
        }

        return 'This server can\'t process iPhone (HEIC) photos right now. Please switch your camera to "Most Compatible" format (Settings > Camera > Formats on your iPhone) and retake the photo, or upload a JPG/PNG instead.';
    }

    protected function imagickSupportsHeic(): bool
    {
        if (!extension_loaded('imagick')) {
            return false;
        }

        try {
            return in_array('HEIC', (new \Imagick())->queryFormats('HEI*'), true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function imageManager(): ImageManager
    {
        // Imagick is a strict superset of GD for our purposes (and is required
        // for HEIC support), so prefer it whenever it's installed.
        $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();

        return new ImageManager($driver);
    }

    /**
     * Resize an uploaded image to fit within $maxWidth x $maxHeight (never
     * upscaling) and store it as a JPG at $path.
     */
    protected function saveResizedJpeg(UploadedFile $file, string $path, int $maxWidth, int $maxHeight): void
    {
        $image = $this->imageManager()->read($file->getRealPath());

        if ($image->width() > $maxWidth || $image->height() > $maxHeight) {
            $scale = min($maxWidth / $image->width(), $maxHeight / $image->height());
            $image->scale((int) ($image->width() * $scale), (int) ($image->height() * $scale));
        }

        $image->toJpeg(90)->save($path);
    }
}
