<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Mpdf\Mpdf;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;

class MenuController extends Controller
{
    public function index(Request $request)
    {
        $query = Menu::query();

        // Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('label', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by menutype
        if ($request->filled('menutype')) {
            $query->where('menutype', $request->menutype);
        }

        // Sorting
        $sortField = $request->get('sort', 'created_at');
        $sortDirection = $request->get('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $menus = $query->paginate(10)->withQueryString();

        return inertia('menus/Index', [
            'menus' => $menus,
            'filters' => [
                'search' => $request->search,
                'menutype' => $request->menutype,
                'sort' => $sortField,
                'direction' => $sortDirection,
            ],
            'labelFormats' => collect(config('labels.formats'))->map(fn ($f, $id) => [
                'id' => $id,
                'name' => $f['name'],
                'size' => rtrim(rtrim(number_format($f['label_width'], 1), '0'), '.') . ' x ' . rtrim(rtrim(number_format($f['label_height'], 1), '0'), '.') . ' mm',
                'count' => $f['cols'] * $f['rows'],
            ])->values(),
            'defaultLabelFormat' => config('labels.default'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'menutype' => 'required|in:normal,menuset',
            // Not Laravel's 'image' rule: that rule's internal mime list doesn't
            // include HEIC/HEIF, which is the default format for iPhone photos.
            'image' => 'nullable|file|max:5120|mimes:jpeg,jpg,png,gif,webp,heic,heif',
        ]);

        if ($request->hasFile('image') && ($error = $this->unsupportedImageError($request->file('image')))) {
            return back()->withErrors(['image' => $error])->withInput();
        }

        $menu = Menu::create($validated);

        // Save and resize image if provided
        if ($request->hasFile('image')) {
            $this->saveAndResizeImage($request->file('image'), $menu->id);
        }

        return redirect()->route('admin.menus_index')
            ->with('success', 'Menu created successfully.');
    }

    public function update(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'menutype' => 'required|in:normal,menuset',
            'image' => 'nullable|file|max:5120|mimes:jpeg,jpg,png,gif,webp,heic,heif',
            'delete_image' => 'nullable|boolean',
        ]);

        if ($request->hasFile('image') && ($error = $this->unsupportedImageError($request->file('image')))) {
            return back()->withErrors(['image' => $error])->withInput();
        }

        // Delete image if requested
        if ($request->boolean('delete_image')) {
            if ($menu->has_image) {
                Storage::disk('public')->delete("menus/{$menu->id}.jpg");
            }
        }

        $menu->update($validated);

        // Save and resize new image if provided (overwrites existing)
        if ($request->hasFile('image')) {
            $this->saveAndResizeImage($request->file('image'), $menu->id);
        }

        return redirect()->route('admin.menus_index')
            ->with('success', 'Menu updated successfully.');
    }

    public function destroy(Menu $menu)
    {
        // Delete image if exists
        if ($menu->has_image) {
            Storage::disk('public')->delete("menus/{$menu->id}.jpg");
        }

        $menu->delete();

        return redirect()->route('admin.menus_index')
            ->with('success', 'Menu deleted successfully.');
    }

    public function labels(Request $request, Menu $menu)
    {
        $validated = $request->validate([
            'format' => 'required|in:' . implode(',', array_keys(config('labels.formats'))),
        ]);

        $format = config('labels.formats.' . $validated['format']);

        // Shrink the font for longer names so they stay inside the label
        $length = mb_strlen($menu->label ?? '');
        $fontSize = match (true) {
            $length <= 12 => 20,
            $length <= 24 => 16,
            $length <= 40 => 13,
            default => 10,
        };
        $fontSize = min($fontSize, max(8, (int) round($format['label_width'] / 3.5)));

        // Estimate the content height (logo + wrapped name) to centre it vertically
        $logoWidth = min($format['label_width'] * 0.45, $format['label_height'] * 0.5);
        $logoHeight = $logoWidth * 172 / 200;
        $textWidth = $format['label_width'] - 8;
        $charWidth = $fontSize * 0.3528 * 0.6;
        $lines = max(1, (int) ceil($length * $charWidth / $textWidth));
        $textHeight = 2 + $lines * $fontSize * 0.3528 * 1.15;
        $offsetTop = max(1, ($format['label_height'] - $logoHeight - $textHeight) / 2);

        $html = view('menus.labels', [
            'menu' => $menu,
            'format' => $format,
            'fontSize' => $fontSize,
            'logoWidth' => round($logoWidth, 1),
            'textWidth' => $textWidth,
            'offsetTop' => round($offsetTop, 1),
        ])->render();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
            'autoScriptToLang' => true,
            'autoLangToFont' => true,
        ]);
        $mpdf->SetDefaultFont('DejaVuSansCondensed');
        $mpdf->WriteHTML($html);

        return response($mpdf->Output('', 'S'), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . \Illuminate\Support\Str::slug($menu->label ?: 'menu') . '.pdf"',
        ]);
    }

    public function show($id)
    {
        return inertia('menus/Show', ['id' => $id]);
    }

    private function isHeicUpload(UploadedFile $file): bool
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
    private function unsupportedImageError(UploadedFile $file): ?string
    {
        if (!$this->isHeicUpload($file)) {
            return null;
        }

        if ($this->imagickSupportsHeic()) {
            return null;
        }

        return 'This server can\'t process iPhone (HEIC) photos right now. Please switch your camera to "Most Compatible" format (Settings > Camera > Formats on your iPhone) and retake the photo, or upload a JPG/PNG instead.';
    }

    private function imagickSupportsHeic(): bool
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

    private function imageManager(): ImageManager
    {
        // Imagick is a strict superset of GD for our purposes (and is required
        // for HEIC support), so prefer it whenever it's installed.
        $driver = extension_loaded('imagick') ? new ImagickDriver() : new GdDriver();

        return new ImageManager($driver);
    }

    /**
     * Save and resize image to fit within 800x600
     */
    private function saveAndResizeImage($file, $menuId)
    {
        $manager = $this->imageManager();

        // Read image from uploaded file
        $image = $manager->read($file->getRealPath());

        // Get original dimensions
        $width = $image->width();
        $height = $image->height();

        // Calculate new dimensions while maintaining aspect ratio
        if ($width > 800 || $height > 600) {
            // Calculate scale factors for both dimensions
            $scaleWidth = 800 / $width;
            $scaleHeight = 600 / $height;
            
            // Use the smaller scale factor to ensure image fits within both constraints
            $scale = min($scaleWidth, $scaleHeight);
            
            $newWidth = (int)($width * $scale);
            $newHeight = (int)($height * $scale);
            
            $image->scale($newWidth, $newHeight);
        }

        // Save as JPG
        $path = storage_path("app/public/menus/{$menuId}.jpg");
        $image->toJpeg(90)->save($path);
    }
}