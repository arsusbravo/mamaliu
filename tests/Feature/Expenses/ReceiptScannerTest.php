<?php

use App\Services\ReceiptScanner;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config(['services.openrouter.key' => 'test-key', 'services.openrouter.model' => 'test/model']);
    $this->photo = UploadedFile::fake()->image('receipt.jpg', 600, 900);
});

/** Make the model answer with the given text (or array, sent as json). */
function modelSays(array|string $answer, int $status = 200): void
{
    $content = is_array($answer) ? json_encode($answer) : $answer;

    Http::fake(['openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => $content]]]], $status)]);
}

function receipt(array $overrides = []): array
{
    return $overrides + [
        'store' => 'Albert Heijn',
        'date' => '2026-11-02',
        'total' => 7.49,
        'items' => [
            ['name' => 'Rice 1kg', 'quantity' => 1, 'price' => 3.49, 'category' => 'Ingredients'],
            ['name' => 'Chicken', 'quantity' => 2, 'price' => 4.00, 'category' => 'Meat'],
        ],
    ];
}

test('a receipt is read into store, date and items', function () {
    modelSays(['receipts' => [receipt()]]);

    $result = (new ReceiptScanner())->scan($this->photo);

    expect($result['receipts'])->toHaveCount(1);

    $found = $result['receipts'][0];

    expect($found['store'])->toBe('Albert Heijn')
        ->and($found['date'])->toBe('2026-11-02')
        ->and($found['total'])->toBe(7.49)
        ->and($found['items'])->toHaveCount(2)
        ->and($found['items'][0])->toBe(['name' => 'Rice 1kg', 'quantity' => 1.0, 'price' => 3.49, 'category' => 'Ingredients']);

    Storage::disk('local')->assertExists($found['receipt_image']);
});

test('the request goes to openrouter with the key, the model and the photo', function () {
    modelSays(['receipts' => [receipt()]]);

    (new ReceiptScanner())->scan($this->photo);

    Http::assertSent(function (Request $request) {
        $body = $request->data();

        return $request->url() === 'https://openrouter.ai/api/v1/chat/completions'
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && $body['model'] === 'test/model'
            && str_starts_with($body['messages'][0]['content'][1]['image_url']['url'], 'data:image/jpeg;base64,');
    });
});

test('several receipts in one photo are returned separately and share the photo', function () {
    modelSays(['receipts' => [
        receipt(['store' => 'Shop A']),
        receipt(['store' => 'Shop B']),
    ]]);

    $result = (new ReceiptScanner())->scan($this->photo);

    expect($result['receipts'])->toHaveCount(2)
        ->and(collect($result['receipts'])->pluck('store')->all())->toBe(['Shop A', 'Shop B'])
        ->and(collect($result['receipts'])->pluck('receipt_image')->unique())->toHaveCount(1);
});

test('a single receipt object is accepted when the model skips the list', function () {
    modelSays(receipt());

    expect((new ReceiptScanner())->scan($this->photo)['receipts'])->toHaveCount(1);
});

test('json wrapped in a markdown fence is still read', function () {
    modelSays("```json\n" . json_encode(['receipts' => [receipt()]]) . "\n```");

    expect((new ReceiptScanner())->scan($this->photo)['receipts'])->toHaveCount(1);
});

test('numbers with a decimal comma are understood', function () {
    modelSays(['receipts' => [receipt([
        'total' => '€ 12,50',
        'items' => [['name' => 'Oil', 'quantity' => '2', 'price' => '3,49', 'category' => 'Other']],
    ])]]);

    $found = (new ReceiptScanner())->scan($this->photo)['receipts'][0];

    expect($found['total'])->toBe(12.5)
        ->and($found['items'][0]['price'])->toBe(3.49)
        ->and($found['items'][0]['quantity'])->toBe(2.0);
});

test('an unknown category becomes empty so the user picks one', function () {
    modelSays(['receipts' => [receipt(['items' => [['name' => 'Thing', 'quantity' => 1, 'price' => 1, 'category' => 'Spaceships']]])]]);

    expect((new ReceiptScanner())->scan($this->photo)['receipts'][0]['items'][0]['category'])->toBeNull();
});

test('lines without a name are dropped and a missing quantity defaults to one', function () {
    modelSays(['receipts' => [receipt(['items' => [
        ['name' => '', 'quantity' => 1, 'price' => 9],
        ['quantity' => 1, 'price' => 9],
        ['name' => 'Kept', 'price' => 2],
    ]])]]);

    $items = (new ReceiptScanner())->scan($this->photo)['receipts'][0]['items'];

    expect($items)->toHaveCount(1)
        ->and($items[0]['name'])->toBe('Kept')
        ->and($items[0]['quantity'])->toBe(1.0);
});

test('negative prices such as discounts are kept', function () {
    modelSays(['receipts' => [receipt(['items' => [
        ['name' => 'Milk', 'quantity' => 1, 'price' => 2],
        ['name' => 'Discount', 'quantity' => 1, 'price' => -0.5],
    ]])]]);

    expect((new ReceiptScanner())->scan($this->photo)['receipts'][0]['items'][1]['price'])->toBe(-0.5);
});

test('an unreadable, old or future date falls back to today', function (mixed $date) {
    modelSays(['receipts' => [receipt(['date' => $date])]]);

    expect((new ReceiptScanner())->scan($this->photo)['receipts'][0]['date'])->toBe(now()->toDateString());
})->with(['garbage' => ['not a date'], 'null' => [null], 'too old' => ['1999-05-05'], 'future' => ['2999-01-01']]);

test('a receipt without items is an error and the photo is removed again', function () {
    modelSays(['receipts' => [receipt(['items' => []])]]);

    expect(fn () => (new ReceiptScanner())->scan($this->photo))->toThrow(RuntimeException::class, 'Could not read');

    expect(Storage::disk('local')->allFiles('receipts'))->toBe([]);
});

test('text that is not json is an error', function () {
    modelSays('Sorry, I cannot read this.');

    expect(fn () => (new ReceiptScanner())->scan($this->photo))->toThrow(RuntimeException::class, 'Could not read');
});

test('a failing scan service is an error and the photo is removed again', function () {
    Http::fake(['openrouter.ai/*' => Http::response('boom', 500)]);

    expect(fn () => (new ReceiptScanner())->scan($this->photo))->toThrow(RuntimeException::class, 'error (500)');

    expect(Storage::disk('local')->allFiles('receipts'))->toBe([]);
});

test('without an api key nothing is sent', function () {
    config(['services.openrouter.key' => null]);
    Http::fake();

    expect(fn () => (new ReceiptScanner())->scan($this->photo))->toThrow(RuntimeException::class, 'OPENROUTER_API_KEY');

    Http::assertNothingSent();
});
