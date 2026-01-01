<?php

namespace App\Http\Controllers;

use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

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
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'menutype' => 'required|in:normal,menuset',
            'image' => 'nullable|image|max:5120', // 5MB max
        ]);

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
            'image' => 'nullable|image|max:5120',
            'delete_image' => 'nullable|boolean',
        ]);

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

    public function show($id)
    {
        return inertia('menus/Show', ['id' => $id]);
    }

    /**
     * Save and resize image to fit within 800x600
     */
    private function saveAndResizeImage($file, $menuId)
    {
        // Create image manager with GD driver
        $manager = new ImageManager(new Driver());
        
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