<?php

use App\Exceptions\DiscountIneligibleException;
use App\Exceptions\DiscountNotFoundException;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\Group;
use App\Models\Menu;
use App\Services\DiscountService;

function discount(array $attributes = []): Discount
{
    return Discount::create($attributes + [
        'code' => 'SAVE' . random_int(1000, 9999),
        'type' => 'percentage',
        'value' => 10,
        'target_type' => 'general',
        'active' => true,
    ]);
}

/** One cart line: weekmenu id, menu id, quantity and unit price. */
function line(int $weekmenuId, int $menuId, int $quantity, float $price): array
{
    return ['weekmenu_id' => $weekmenuId, 'menu_id' => $menuId, 'quantity' => $quantity, 'unit_price' => $price];
}

function redeemOnce(Discount $discount, $user): void
{
    DiscountRedemption::create([
        'discount_id' => $discount->id, 'user_id' => $user->id, 'group_id' => null,
        'week' => 1, 'year' => 2026, 'order_total_before' => 10, 'discount_amount' => 1,
    ]);
}

beforeEach(function () {
    $this->service = new DiscountService();
    $this->user = client();
});

test('a code is found when it exists and is active', function () {
    $discount = discount(['code' => 'WELCOME']);

    expect($this->service->findValidCode('WELCOME')->id)->toBe($discount->id);
});

test('unknown and inactive codes are not found', function () {
    discount(['code' => 'OLD', 'active' => false]);

    expect(fn () => $this->service->findValidCode('NOPE'))->toThrow(DiscountNotFoundException::class)
        ->and(fn () => $this->service->findValidCode('OLD'))->toThrow(DiscountNotFoundException::class);
});

test('a code outside its date window is not eligible', function (array $dates) {
    $discount = discount($dates);

    expect(fn () => $this->service->checkEligibility($discount, $this->user, []))
        ->toThrow(DiscountIneligibleException::class, 'not valid at this time');
})->with([
    'expired' => [['valid_until' => '2020-01-01 00:00:00']],
    'not started' => [['valid_from' => '2999-01-01 00:00:00']],
]);

test('a code inside its date window is eligible', function () {
    $discount = discount(['valid_from' => now()->subDay(), 'valid_until' => now()->addDay()]);

    $this->service->checkEligibility($discount, $this->user, []);

    expect(true)->toBeTrue();
});

test('a user code only works for that user', function () {
    $discount = discount(['target_type' => 'user', 'user_id' => $this->user->id]);

    $this->service->checkEligibility($discount, $this->user, []);

    expect(fn () => $this->service->checkEligibility($discount, client(), []))
        ->toThrow(DiscountIneligibleException::class, 'your account');
});

test('a group code only works for that group', function () {
    $group = Group::factory()->create();
    $discount = discount(['target_type' => 'group', 'group_id' => $group->id]);
    $member = client(['group_id' => $group->id]);

    $this->service->checkEligibility($discount, $member, []);

    expect(fn () => $this->service->checkEligibility($discount, $this->user, []))
        ->toThrow(DiscountIneligibleException::class, 'your group');
});

test('a menu code needs that menu in the cart', function () {
    $menu = Menu::factory()->create();
    $discount = discount(['target_type' => 'menu', 'menu_id' => $menu->id]);

    $this->service->checkEligibility($discount, $this->user, [line(1, $menu->id, 1, 10)]);

    expect(fn () => $this->service->checkEligibility($discount, $this->user, [line(2, $menu->id + 1, 1, 10)]))
        ->toThrow(DiscountIneligibleException::class, "isn't in your cart");
});

test('a minimum order total is enforced', function () {
    $discount = discount(['min_order_total' => 20]);

    $this->service->checkEligibility($discount, $this->user, [line(1, 1, 2, 10)]);

    expect(fn () => $this->service->checkEligibility($discount, $this->user, [line(1, 1, 1, 10)]))
        ->toThrow(DiscountIneligibleException::class, 'minimum order total');
});

test('the global usage limit is enforced', function () {
    $discount = discount(['usage_limit' => 2]);
    redeemOnce($discount, client());

    $this->service->checkEligibility($discount, $this->user, []);

    redeemOnce($discount, client());

    expect(fn () => $this->service->checkEligibility($discount, $this->user, []))
        ->toThrow(DiscountIneligibleException::class, 'usage limit');
});

test('the per user usage limit is enforced', function () {
    $discount = discount(['usage_limit_per_user' => 1]);
    redeemOnce($discount, client());

    $this->service->checkEligibility($discount, $this->user, []);

    redeemOnce($discount, $this->user);

    expect(fn () => $this->service->checkEligibility($discount, $this->user, []))
        ->toThrow(DiscountIneligibleException::class, 'maximum number of times');
});

test('a percentage discount is taken from the whole cart', function () {
    $discount = discount(['type' => 'percentage', 'value' => 10]);

    $result = $this->service->calculateDiscount($discount, [line(1, 1, 2, 10), line(2, 2, 1, 20)]);

    expect($result['total_discount'])->toBe(4.0)
        ->and($result['per_line'])->toBe([1 => 2.0, 2 => 2.0]);
});

test('a fixed discount can not exceed the cart total', function () {
    $discount = discount(['type' => 'fixed', 'value' => 50]);

    $result = $this->service->calculateDiscount($discount, [line(1, 1, 1, 12.5)]);

    expect($result['total_discount'])->toBe(12.5);
});

test('a menu discount only applies to that menu', function () {
    $menu = Menu::factory()->create();
    $discount = discount(['type' => 'percentage', 'value' => 50, 'target_type' => 'menu', 'menu_id' => $menu->id]);

    $result = $this->service->calculateDiscount($discount, [line(1, $menu->id, 1, 10), line(2, $menu->id + 1, 1, 100)]);

    expect($result['total_discount'])->toBe(5.0)
        ->and($result['per_line'])->toBe([1 => 5.0]);
});

test('line amounts always add up to the total even when rounding', function () {
    $discount = discount(['type' => 'fixed', 'value' => 10]);

    $result = $this->service->calculateDiscount($discount, [line(1, 1, 1, 10), line(2, 2, 1, 10), line(3, 3, 1, 10)]);

    expect(round(array_sum($result['per_line']), 2))->toBe(10.0)
        ->and($result['total_discount'])->toBe(10.0);
});

test('an empty cart gives no discount', function () {
    expect($this->service->calculateDiscount(discount(), []))
        ->toBe(['total_discount' => 0.0, 'per_line' => []]);
});

test('redeeming records the use', function () {
    $discount = discount();

    $this->service->redeem($discount, $this->user, 12, 2026, 40.0, 4.0);

    expect(DiscountRedemption::where('discount_id', $discount->id)->count())->toBe(1)
        ->and(DiscountRedemption::first()->discount_amount)->toEqual(4.0);
});
