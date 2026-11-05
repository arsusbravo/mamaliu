<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use App\Support\HandlesImageUploads;
use App\Support\LabelSheet;

class MenuController extends Controller
{
    use HandlesImageUploads;

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
            'text' => 'nullable|string|max:255',
        ]);

        // Optional one-off override of the printed text; the menu itself is not changed
        $text = trim($validated['text'] ?? '') !== '' ? trim($validated['text']) : ($menu->label ?? '');

        $pdf = LabelSheet::pdf(config('labels.formats.' . $validated['format']), $text);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . \Illuminate\Support\Str::slug($text ?: 'menu') . '.pdf"',
        ]);
    }

    public function show($id)
    {
        return inertia('menus/Show', ['id' => $id]);
    }

    /**
     * Save and resize image to fit within 800x600
     */
    private function saveAndResizeImage($file, $menuId)
    {
        $this->saveResizedJpeg($file, storage_path("app/public/menus/{$menuId}.jpg"), 800, 600);
    }
}
