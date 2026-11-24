<?php

use App\Exceptions\GiftIneligibleException;
use App\Exceptions\GiftNotFoundException;
use App\Models\Gift;
use App\Models\GiftRedemption;
use App\Models\Menu;
use App\Services\GiftService;

function gift(array $attributes = []): Gift
{
    return Gift::create($attributes + [
        'threshold_amount' => 25,
        'reward_menu_id' => Menu::factory()->create()->id,
        'active' => true,
    ]);
}

function giftLine(int $quantity, float $price): array
{
    return ['weekmenu_id' => 1, 'menu_id' => 1, 'quantity' => $quantity, 'unit_price' => $price];
}

beforeEach(function () {
    $this->service = new GiftService();
    $this->member = client(['member_code' => 'M-1001']);
});

test('the current gift is found', function () {
    $gift = gift();

    expect($this->service->findActiveGift()->id)->toBe($gift->id);
});

test('no gift is found when none is active or in its window', function (array $attributes) {
    gift($attributes);

    expect(fn () => $this->service->findActiveGift())->toThrow(GiftNotFoundException::class);
})->with([
    'inactive' => [['active' => false]],
    'expired' => [['valid_until' => '2020-01-01 00:00:00']],
    'not started' => [['valid_from' => '2999-01-01 00:00:00']],
]);

test('no gift exists at all', function () {
    expect(fn () => $this->service->findActiveGift())->toThrow(GiftNotFoundException::class);
});

test('a member with the right ID and enough spend is eligible', function () {
    $this->service->checkEligibility(gift(), $this->member, 'M-1001', [giftLine(3, 10)]);

    expect(true)->toBeTrue();
});

test('the ID may have spaces around it', function () {
    $this->service->checkEligibility(gift(), $this->member, '  M-1001 ', [giftLine(3, 10)]);

    expect(true)->toBeTrue();
});

test('a wrong ID is rejected', function () {
    expect(fn () => $this->service->checkEligibility(gift(), $this->member, 'WRONG', [giftLine(3, 10)]))
        ->toThrow(GiftIneligibleException::class, 'Incorrect ID');
});

test('a client without a member code is rejected', function () {
    expect(fn () => $this->service->checkEligibility(gift(), client(), 'M-1001', [giftLine(3, 10)]))
        ->toThrow(GiftIneligibleException::class, 'No membership ID');
});

test('spending below the threshold is rejected', function () {
    expect(fn () => $this->service->checkEligibility(gift(), $this->member, 'M-1001', [giftLine(2, 10)]))
        ->toThrow(GiftIneligibleException::class, 'minimum order');
});

test('an inactive gift is rejected even with a valid ID', function () {
    expect(fn () => $this->service->checkEligibility(gift(['active' => false]), $this->member, 'M-1001', [giftLine(3, 10)]))
        ->toThrow(GiftIneligibleException::class, 'no gift available');
});

test('one gift is given per full threshold spent', function (float $spend, int $expected) {
    $result = $this->service->calculateGift(gift(['threshold_amount' => 25]), [giftLine(1, $spend)]);

    expect($result['quantity'])->toBe($expected)
        ->and($result['qualifying_total'])->toBe($spend);
})->with([
    'just under' => [24.99, 0],
    'exactly one' => [25.0, 1],
    'one and a half' => [40.0, 1],
    'two' => [50.0, 2],
    'two and a bit' => [74.0, 2],
]);

test('redeeming records the gift', function () {
    $gift = gift();

    $this->service->redeem($gift, $this->member, 12, 2026, 50.0, 2);

    expect(GiftRedemption::count())->toBe(1)
        ->and(GiftRedemption::first()->gift_quantity)->toBe(2);
});
