<?php

use App\Models\Discount;
use App\Services\DiscountService;

/*
 * Pure arithmetic: no database, models are built but never saved.
 */

function calcDiscount(array $attributes): Discount
{
    return new Discount($attributes + ['type' => 'percentage', 'value' => 10, 'target_type' => 'general']);
}

function calcLine(int $weekmenuId, int $menuId, int $quantity, float $price): array
{
    return ['weekmenu_id' => $weekmenuId, 'menu_id' => $menuId, 'quantity' => $quantity, 'unit_price' => $price];
}

test('100 percent takes off the whole cart', function () {
    $result = (new DiscountService())->calculateDiscount(
        calcDiscount(['value' => 100]),
        [calcLine(1, 1, 2, 10), calcLine(2, 2, 1, 5)]
    );

    expect($result['total_discount'])->toBe(25.0)
        ->and($result['per_line'])->toBe([1 => 20.0, 2 => 5.0]);
});

test('free items give no discount and no division by zero', function () {
    $result = (new DiscountService())->calculateDiscount(calcDiscount([]), [calcLine(1, 1, 3, 0)]);

    expect($result)->toBe(['total_discount' => 0.0, 'per_line' => []]);
});

test('a menu discount with no matching line gives nothing', function () {
    $discount = calcDiscount(['target_type' => 'menu', 'menu_id' => 99]);

    $result = (new DiscountService())->calculateDiscount($discount, [calcLine(1, 1, 1, 10)]);

    expect($result)->toBe(['total_discount' => 0.0, 'per_line' => []]);
});

test('the order of the cart lines does not change the shares', function () {
    $discount = calcDiscount(['type' => 'fixed', 'value' => 10]);
    $lines = [calcLine(3, 3, 1, 10), calcLine(1, 1, 1, 10), calcLine(2, 2, 1, 10)];

    $forward = (new DiscountService())->calculateDiscount($discount, $lines);
    $backward = (new DiscountService())->calculateDiscount($discount, array_reverse($lines));

    expect($backward['per_line'])->toEqual($forward['per_line']);
});

test('the shares always add up to the total, for many random carts', function () {
    mt_srand(2026);
    $service = new DiscountService();

    foreach (range(1, 300) as $round) {
        $lines = [];

        foreach (range(1, mt_rand(1, 8)) as $id) {
            $lines[] = calcLine($id, $id, mt_rand(1, 5), mt_rand(1, 3000) / 100);
        }

        $discount = calcDiscount(mt_rand(0, 1)
            ? ['type' => 'percentage', 'value' => mt_rand(1, 100)]
            : ['type' => 'fixed', 'value' => mt_rand(1, 20000) / 100]);

        $result = $service->calculateDiscount($discount, $lines);
        $subtotal = array_sum(array_map(fn ($l) => $l['unit_price'] * $l['quantity'], $lines));

        expect(round(array_sum($result['per_line']), 2))->toBe($result['total_discount'])
            ->and($result['total_discount'])->toBeLessThanOrEqual(round($subtotal, 2));
    }
});

test('no line is ever given a negative share', function () {
    mt_srand(2026);
    $service = new DiscountService();

    foreach (range(1, 300) as $round) {
        $lines = [];

        foreach (range(1, mt_rand(2, 8)) as $id) {
            $lines[] = calcLine($id, $id, 1, 10);
        }

        $result = $service->calculateDiscount(calcDiscount(['type' => 'fixed', 'value' => mt_rand(1, 20) / 100]), $lines);

        foreach ($result['per_line'] as $share) {
            expect($share)->toBeGreaterThanOrEqual(0.0);
        }
    }
});
