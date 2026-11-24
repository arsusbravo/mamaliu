<?php

use App\Models\Expense;
use App\Models\ExpenseItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(fn () => $this->actingAs(admin()));

const PHOTO = 'receipts/11111111-1111-1111-1111-111111111111.jpg';
const OTHER_PHOTO = 'receipts/22222222-2222-2222-2222-222222222222.jpg';

function expenseData(array $overrides = []): array
{
    return $overrides + [
        'store' => 'Albert Heijn',
        'purchased_at' => '2026-11-02',
        'total' => 7.49,
        'items' => [
            ['name' => 'Rice', 'quantity' => 1, 'price' => 3.49, 'category' => 'Ingredients'],
            ['name' => 'Chicken', 'quantity' => 2, 'price' => 4.00, 'category' => 'Meat'],
        ],
    ];
}

/** Save an expense straight to the database. */
function expense(string $date, array $items, ?string $photo = null, string $store = 'Shop'): Expense
{
    $expense = Expense::create(['store' => $store, 'purchased_at' => $date, 'receipt_image' => $photo]);
    $expense->items()->createMany($items);

    return $expense;
}

function item(string $name, float $price, ?string $category = null): array
{
    return ['name' => $name, 'quantity' => 1, 'price' => $price, 'category' => $category];
}

test('an expense is saved with its items', function () {
    $this->post(route('admin.expenses_store'), expenseData(['receipt_image' => PHOTO]))
        ->assertRedirect(route('admin.expenses_index'))
        ->assertSessionHas('success');

    $saved = Expense::with('items')->first();

    expect($saved->store)->toBe('Albert Heijn')
        ->and($saved->purchased_at->toDateString())->toBe('2026-11-02')
        ->and($saved->receipt_image)->toBe(PHOTO)
        ->and($saved->items)->toHaveCount(2)
        ->and($saved->items_total)->toBe(7.49);
});

test('expense input is validated', function (array $bad, string $field) {
    $this->post(route('admin.expenses_store'), expenseData($bad))->assertSessionHasErrors($field);

    expect(Expense::count())->toBe(0);
})->with([
    'date is required' => [['purchased_at' => ''], 'purchased_at'],
    'needs at least one item' => [['items' => []], 'items'],
    'item name is required' => [['items' => [['name' => '', 'quantity' => 1, 'price' => 1]]], 'items.0.name'],
    'quantity must be positive' => [['items' => [['name' => 'x', 'quantity' => 0, 'price' => 1]]], 'items.0.quantity'],
    'price must be a number' => [['items' => [['name' => 'x', 'quantity' => 1, 'price' => 'free']]], 'items.0.price'],
    'category must be a known one' => [['items' => [['name' => 'x', 'quantity' => 1, 'price' => 1, 'category' => 'Spaceships']]], 'items.0.category'],
    'photo path must come from the scanner' => [['receipt_image' => '../../.env'], 'receipt_image'],
    'photo path may not leave the receipts folder' => [['receipt_image' => 'receipts/../secret.jpg'], 'receipt_image'],
]);

test('an expense can be saved without a photo', function () {
    $this->post(route('admin.expenses_store'), expenseData())->assertSessionHasNoErrors();

    expect(Expense::first()->has_receipt)->toBeFalse();
});

test('updating replaces the items', function () {
    $saved = expense('2026-11-02', [item('Old', 1), item('Older', 2)]);

    $this->put(route('admin.expenses_update', $saved), expenseData(['store' => 'Lidl', 'items' => [item('New', 5)]]))
        ->assertSessionHas('success');

    expect($saved->fresh()->store)->toBe('Lidl')
        ->and($saved->items()->pluck('name')->all())->toBe(['New'])
        ->and(ExpenseItem::count())->toBe(1);
});

test('deleting an expense removes it and its items', function () {
    $saved = expense('2026-11-02', [item('Rice', 3)]);

    $this->delete(route('admin.expenses_destroy', $saved))->assertSessionHas('success');

    expect(Expense::count())->toBe(0)->and(ExpenseItem::count())->toBe(0);
});

test('a photo shared by several receipts is only deleted with the last one', function () {
    Storage::disk('local')->put(PHOTO, 'jpeg');
    $first = expense('2026-11-02', [item('A', 1)], PHOTO);
    $second = expense('2026-11-02', [item('B', 2)], PHOTO);

    $this->delete(route('admin.expenses_destroy', $first));
    Storage::disk('local')->assertExists(PHOTO);

    $this->delete(route('admin.expenses_destroy', $second));
    Storage::disk('local')->assertMissing(PHOTO);
});

test('another receipts photo is left alone', function () {
    Storage::disk('local')->put(PHOTO, 'jpeg');
    Storage::disk('local')->put(OTHER_PHOTO, 'jpeg');
    $first = expense('2026-11-02', [item('A', 1)], PHOTO);
    expense('2026-11-02', [item('B', 2)], OTHER_PHOTO);

    $this->delete(route('admin.expenses_destroy', $first));

    Storage::disk('local')->assertMissing(PHOTO);
    Storage::disk('local')->assertExists(OTHER_PHOTO);
});

test('admins can open a receipt photo, nobody else', function () {
    Storage::disk('local')->put(PHOTO, 'jpeg-bytes');
    $saved = expense('2026-11-02', [item('A', 1)], PHOTO);

    $this->get(route('admin.expenses_receipt', $saved))->assertOk();

    auth()->logout();
    $this->get(route('admin.expenses_receipt', $saved))->assertRedirect(route('login'));

    $this->actingAs(client())->get(route('admin.expenses_receipt', $saved))->assertForbidden();
});

test('a receipt without a stored photo is a 404', function () {
    $saved = expense('2026-11-02', [item('A', 1)]);

    $this->get(route('admin.expenses_receipt', $saved))->assertNotFound();
});

test('the list shows totals per category for the whole range', function () {
    expense('2026-11-02', [item('Rice', 10, 'Ingredients'), item('Beef', 30, 'Meat')]);
    expense('2026-11-20', [item('Bags', 5, 'Packaging'), item('Flour', 15, 'Ingredients')]);

    $totals = $this->get(route('admin.expenses_index'))->viewData('page')['props']['totals'];

    expect($totals['overall'])->toEqual(60)
        ->and(collect($totals['by_category'])->pluck('total', 'category')->all())
        ->toEqual(['Meat' => 30, 'Ingredients' => 25, 'Packaging' => 5]);
});

test('items without a category are totalled as uncategorised', function () {
    expense('2026-11-02', [item('Mystery', 8)]);

    $totals = $this->get(route('admin.expenses_index'))->viewData('page')['props']['totals'];

    expect(collect($totals['by_category'])->pluck('total', 'category')->all())->toEqual(['Uncategorised' => 8]);
});

test('the list can be limited to a period', function () {
    expense('2026-10-31', [item('October', 100, 'Meat')]);
    expense('2026-11-02', [item('Early', 10, 'Meat')]);
    expense('2026-11-30', [item('Late', 20, 'Meat')]);
    expense('2026-12-01', [item('December', 1000, 'Meat')]);

    $props = $this->get(route('admin.expenses_index', ['from' => '2026-11-01', 'to' => '2026-11-30']))
        ->viewData('page')['props'];

    expect($props['totals']['overall'])->toEqual(30)
        ->and(collect($props['expenses']['data'])->pluck('items')->flatten(1)->pluck('name')->sort()->values()->all())
        ->toBe(['Early', 'Late']);
});

test('the list can be limited to a category', function () {
    expense('2026-11-02', [item('Rice', 10, 'Ingredients'), item('Beef', 30, 'Meat')]);

    $totals = $this->get(route('admin.expenses_index', ['category' => 'Meat']))->viewData('page')['props']['totals'];

    expect($totals['overall'])->toEqual(30);
});

test('the list shows whether the scanner is configured', function () {
    config(['services.openrouter.key' => null]);
    expect($this->get(route('admin.expenses_index'))->viewData('page')['props']['scannerConfigured'])->toBeFalse();

    config(['services.openrouter.key' => 'abc']);
    expect($this->get(route('admin.expenses_index'))->viewData('page')['props']['scannerConfigured'])->toBeTrue();
});

test('the export has one row per item and follows the filters', function () {
    expense('2026-11-02', [item('Rice', 10, 'Ingredients'), item('Beef', 30, 'Meat')], null, 'Shop A');
    expense('2026-12-05', [item('Bags', 5, 'Packaging')], null, 'Shop B');

    $rows = function (array $query) {
        $response = $this->get(route('admin.expenses_export', $query))->assertOk();
        $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

        return $sheet->toArray(null, true, true, false);
    };

    $all = $rows([]);
    expect($all[0])->toBe(['Date', 'Store', 'Item', 'Category', 'Qty', 'Price'])
        ->and($all)->toHaveCount(4);

    $meat = $rows(['category' => 'Meat']);
    expect(collect($meat)->pluck(2)->all())->toContain('Beef')->not->toContain('Rice');

    $november = $rows(['from' => '2026-11-01', 'to' => '2026-11-30']);
    expect(collect($november)->pluck(2)->all())->not->toContain('Bags');
});

test('scanning a photo returns the receipts as json for review', function () {
    config(['services.openrouter.key' => 'k']);
    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode([
        'receipts' => [['store' => 'Lidl', 'date' => '2026-11-02', 'total' => 3.5, 'items' => [
            ['name' => 'Rice', 'quantity' => 1, 'price' => 3.5, 'category' => 'Ingredients'],
        ]]],
    ])]]]])]);

    $this->postJson(route('admin.expenses_scan'), ['receipt' => UploadedFile::fake()->image('r.jpg', 500, 800)])
        ->assertOk()
        ->assertJsonPath('receipts.0.store', 'Lidl')
        ->assertJsonPath('receipts.0.items.0.name', 'Rice');
});

test('a scan that fails answers with a readable message', function () {
    config(['services.openrouter.key' => null]);

    $this->postJson(route('admin.expenses_scan'), ['receipt' => UploadedFile::fake()->image('r.jpg', 500, 800)])
        ->assertStatus(422)
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'OPENROUTER_API_KEY'));
});

test('scanning needs an image file', function () {
    $this->postJson(route('admin.expenses_scan'), [])->assertStatus(422)->assertJsonValidationErrors('receipt');
    $this->postJson(route('admin.expenses_scan'), ['receipt' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertStatus(422)->assertJsonValidationErrors('receipt');
});

test('clients can not use expenses', function () {
    $this->actingAs(client());

    $this->post(route('admin.expenses_store'), expenseData())->assertForbidden();
    $this->postJson(route('admin.expenses_scan'), [])->assertForbidden();
    expect(Expense::count())->toBe(0);
});
