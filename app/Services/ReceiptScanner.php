<?php

namespace App\Services;

use App\Support\HandlesImageUploads;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads a receipt photo with a vision model (through OpenRouter) and returns
 * the store, date and line items as plain data for the user to review.
 */
class ReceiptScanner
{
    use HandlesImageUploads;

    private const ENDPOINT = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * One photo can show several small receipts, so this returns a list: every
     * entry carries the stored photo path (shared by the receipts of that photo).
     *
     * @return array{receipts: array<int, array{store: ?string, date: string, total: ?float, items: array, receipt_image: string}>}
     */
    public function scan(UploadedFile $file): array
    {
        $key = config('services.openrouter.key');
        if (! $key) {
            throw new RuntimeException('The receipt scanner is not configured: OPENROUTER_API_KEY is missing.');
        }

        if ($error = $this->unsupportedImageError($file)) {
            throw new RuntimeException($error);
        }

        // Keep a resized copy of the photo with the expense; it is also what we send to the model.
        $path = 'receipts/' . Str::uuid() . '.jpg';
        Storage::disk('local')->makeDirectory('receipts');
        $this->saveResizedJpeg($file, Storage::disk('local')->path($path), 1600, 1600);

        $response = Http::withToken($key)
            ->timeout(90)
            ->acceptJson()
            ->post(self::ENDPOINT, [
                'model' => config('services.openrouter.model'),
                'temperature' => 0,
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $this->prompt()],
                        ['type' => 'image_url', 'image_url' => [
                            'url' => 'data:image/jpeg;base64,' . base64_encode(Storage::disk('local')->get($path)),
                        ]],
                    ],
                ]],
                'response_format' => ['type' => 'json_object'],
            ]);

        if ($response->failed()) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('The scan service returned an error (' . $response->status() . '). Please try again or enter the receipt manually.');
        }

        $data = $this->decode((string) $response->json('choices.0.message.content'));

        // Tolerate a model answering with a single receipt object instead of {"receipts": [...]}
        $found = is_array($data) ? ($data['receipts'] ?? (isset($data['items']) ? [$data] : [])) : [];

        $receipts = collect($found)
            ->filter(fn ($receipt) => is_array($receipt))
            ->map(fn ($receipt) => $this->normalise($receipt) + ['receipt_image' => $path])
            ->filter(fn ($receipt) => count($receipt['items']) > 0)
            ->values()
            ->all();

        if (count($receipts) === 0) {
            Storage::disk('local')->delete($path);
            throw new RuntimeException('Could not read this receipt. Try a sharper, well-lit photo or enter it manually.');
        }

        return ['receipts' => $receipts];
    }

    private function prompt(): string
    {
        $categories = implode(', ', config('expenses.categories'));

        return <<<PROMPT
You read shop receipts for a small food business. The photo can show ONE receipt or SEVERAL separate (often small) receipts next to or below each other. Extract every receipt and answer with ONLY a JSON object:

{"receipts": [{"store": string|null, "date": "YYYY-MM-DD"|null, "total": number|null, "items": [{"name": string, "quantity": number, "price": number, "category": string}]}]}

Rules:
- One entry in "receipts" per separate receipt; never merge items of different receipts. A photo with one receipt gives a list of one.
- "name": the item name exactly as printed (keep Dutch/Chinese/other languages, do not translate).
- "price": the TOTAL price of that line (quantity x unit price), as a plain number with a dot as decimal separator, e.g. 3.49.
- "quantity": number of units (default 1). For weighed goods use the weight in kg if shown, otherwise 1.
- Discounts or deposit refunds on a line: put them in that item's price; a separate discount line has a negative price.
- Do NOT include subtotal, total, VAT/BTW, payment method, change, loyalty points or card lines as items.
- "total": the final amount paid, if visible.
- "date": the purchase date on the receipt. Dates on Dutch receipts are day-month-year.
- "category": exactly one of: {$categories}.
- If you cannot read something, use null instead of guessing.
PROMPT;
    }

    private function decode(string $content): ?array
    {
        // Some models wrap JSON in a markdown fence even when asked not to
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));
        $data = json_decode($content, true);

        return is_array($data) ? $data : null;
    }

    private function normalise(array $data): array
    {
        $categories = config('expenses.categories');

        $items = collect($data['items'] ?? [])
            ->filter(fn ($item) => is_array($item) && filled($item['name'] ?? null))
            ->map(function ($item) use ($categories) {
                $category = $item['category'] ?? null;

                return [
                    'name' => Str::limit(trim((string) $item['name']), 255, ''),
                    'quantity' => $this->number($item['quantity'] ?? 1) ?? 1.0,
                    'price' => $this->number($item['price'] ?? 0) ?? 0.0,
                    'category' => in_array($category, $categories, true) ? $category : null,
                ];
            })
            ->values()
            ->all();

        return [
            'store' => filled($data['store'] ?? null) ? Str::limit(trim((string) $data['store']), 255, '') : null,
            'date' => $this->date($data['date'] ?? null),
            'total' => $this->number($data['total'] ?? null),
            'items' => $items,
        ];
    }

    /** Accepts 3.49, "3.49" and "3,49". */
    private function number(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_string($value)) {
            $value = str_replace(',', '.', preg_replace('/[^\d,.\-]/', '', $value));
        }

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    /** Falls back to today when the model could not read a valid date. */
    private function date(mixed $value): string
    {
        try {
            $date = Carbon::parse((string) $value);
            if ($date->year >= 2000 && $date->lte(now()->addDay())) {
                return $date->toDateString();
            }
        } catch (\Throwable) {
            // fall through
        }

        return now()->toDateString();
    }
}
