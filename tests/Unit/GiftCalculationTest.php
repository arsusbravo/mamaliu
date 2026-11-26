<?php

use App\Models\Gift;
use App\Services\GiftService;

/*
 * Pure arithmetic: no database, the model is built but never saved.
 */

function calcGift(float $threshold): Gift
{
    return new Gift(['threshold_amount' => $threshold]);
}

function giftCartLine(int $quantity, float $price): array
{
    return ['weekmenu_id' => 1, 'menu_id' => 1, 'quantity' => $quantity, 'unit_price' => $price];
}

test('the total is spread over all lines, not counted per line', function () {
    // 3 x 9.00 + 2 x 6.00 = 39.00, one full step of 25
    $result = (new GiftService())->calculateGift(calcGift(25), [giftCartLine(3, 9), giftCartLine(2, 6)]);

    expect($result['qualifying_total'])->toBe(39.0)
        ->and($result['quantity'])->toBe(1);
});

test('an empty cart earns nothing', function () {
    $result = (new GiftService())->calculateGift(calcGift(25), []);

    expect($result['qualifying_total'])->toBe(0.0)
        ->and($result['quantity'])->toBe(0);
});

test('hitting the threshold exactly counts, a cent less does not', function () {
    $service = new GiftService();

    expect($service->calculateGift(calcGift(25), [giftCartLine(1, 25)])['quantity'])->toBe(1)
        ->and($service->calculateGift(calcGift(25), [giftCartLine(1, 24.99)])['quantity'])->toBe(0);
});

test('a bigger cart never earns fewer gifts', function () {
    $service = new GiftService();
    $previous = 0;

    foreach (range(1, 40) as $quantity) {
        $earned = $service->calculateGift(calcGift(25), [giftCartLine($quantity, 7.5)])['quantity'];

        expect($earned)->toBeGreaterThanOrEqual($previous);
        $previous = $earned;
    }
});
