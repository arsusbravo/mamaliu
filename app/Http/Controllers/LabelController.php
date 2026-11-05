<?php

namespace App\Http\Controllers;

use App\Models\Label;
use App\Support\HandlesImageUploads;
use App\Support\LabelSheet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LabelController extends Controller
{
    use HandlesImageUploads;

    public function index()
    {
        return inertia('labels/Index', [
            'labels' => Label::orderBy('created_at', 'desc')->paginate(10)->withQueryString(),
            'labelFormats' => collect(config('labels.formats'))->map(fn ($f, $id) => [
                'id' => $id,
                'name' => $f['name'],
                'size' => rtrim(rtrim(number_format($f['label_width'], 1), '0'), '.') . ' x ' . rtrim(rtrim(number_format($f['label_height'], 1), '0'), '.') . ' mm',
                'count' => $f['cols'] * $f['rows'],
            ])->values(),
            'defaultLabelFormat' => config('labels.default'),
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'text' => 'nullable|string|max:1000',
            'show_logo' => 'required|boolean',
            'text_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            // Not Laravel's 'image' rule: it doesn't accept HEIC/HEIF (iPhone photos).
            'background' => 'nullable|file|max:8192|mimes:jpeg,jpg,png,gif,webp,heic,heif',
            'delete_background' => 'nullable|boolean',
        ];
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        if ($request->hasFile('background') && ($error = $this->unsupportedImageError($request->file('background')))) {
            return back()->withErrors(['background' => $error])->withInput();
        }

        $label = Label::create($this->attributes($validated));
        $this->storeBackground($request, $label);

        return redirect()->route('admin.labels_index')->with('success', 'Label created successfully.');
    }

    public function update(Request $request, Label $label)
    {
        $validated = $request->validate($this->rules());

        if ($request->hasFile('background') && ($error = $this->unsupportedImageError($request->file('background')))) {
            return back()->withErrors(['background' => $error])->withInput();
        }

        $label->update($this->attributes($validated));

        if ($request->boolean('delete_background')) {
            Storage::disk('public')->delete($label->backgroundPath());
        }
        $this->storeBackground($request, $label);

        return redirect()->route('admin.labels_index')->with('success', 'Label updated successfully.');
    }

    public function destroy(Label $label)
    {
        Storage::disk('public')->delete($label->backgroundPath());
        $label->delete();

        return redirect()->route('admin.labels_index')->with('success', 'Label deleted successfully.');
    }

    public function pdf(Request $request, Label $label)
    {
        $validated = $request->validate([
            'format' => 'required|in:' . implode(',', array_keys(config('labels.formats'))),
        ]);

        $pdf = LabelSheet::pdf(
            config('labels.formats.' . $validated['format']),
            $label->name,
            $label->text,
            $label->show_logo,
            $label->text_color,
            $label->getBackgroundFilePath(),
        );

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="label-' . Str::slug($label->name ?: 'label') . '.pdf"',
        ]);
    }

    public function background(Label $label)
    {
        abort_unless($label->has_background, 404);

        return response()->file($label->getBackgroundFilePath());
    }

    private function attributes(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'text' => $validated['text'] ?? null,
            'show_logo' => $validated['show_logo'],
            'text_color' => strtolower($validated['text_color']),
        ];
    }

    private function storeBackground(Request $request, Label $label): void
    {
        if (! $request->hasFile('background')) {
            return;
        }

        Storage::disk('public')->makeDirectory('labels');
        // Large enough for ~300 dpi printing of the biggest preset (A6 landscape-ish)
        $this->saveResizedJpeg($request->file('background'), storage_path('app/public/' . $label->backgroundPath()), 1600, 1600);
    }
}
