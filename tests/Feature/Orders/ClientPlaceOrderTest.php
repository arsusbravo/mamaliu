<?php

use App\Mail\OrderConfirmation;
use App\Models\Discount;
use App\Models\DiscountRedemption;
use App\Models\Gift;
use App\Models\GiftRedemption;
use App\Models\Group;
use App\Models\Menu;
use App\Models\Order;
use App\Models\PickupPoint;
use Illuminate\Support\Facades\Mail;

/*
 * Note: sqlite ignores lockForUpdate() and tests run one request at a time, so
 * these check the stock and rollback logic of checkout, not true concurrency.
 */

beforeEach(function () {
    Mail::fake();
    $this->customer = client(['member_code' => 'M-1001']);
    $this->actingAs($this->customer);
    $this->menu = Menu::factory()->create(['price' => 10]);
    $this->weekmenu = weekmenuWithStock(10, ['menu_id' => $this->menu->id]);
});

function checkout($test, array $lines, array $extra = [])
{
    return $test->post(route('place_order'), $extra + ['orders' => $lines]);
}

function cartLine($weekmenu, int $quantity, ?string $notes = null): array
{
    return ['weekmenu_id' => $weekmenu->id, 'quantity' => $quantity, 'notes' => $notes];
}

test('checkout creates the order, takes the stock and sends a confirmation', function () {
    checkout($this, [cartLine($this->weekmenu, 3, 'no onions')])->assertRedirect('/orders');

    $order = Order::first();

    expect($order->user_id)->toBe($this->customer->id)
        ->and($order->quantity)->toBe(3)
        ->and($order->notes)->toBe('no onions')
        ->and($order->week)->toBe($this->weekmenu->week)
        ->and($order->year)->toBe($this->weekmenu->year)
        ->and($this->weekmenu->fresh()->quantity)->toBe(7);

    Mail::assertSent(OrderConfirmation::class, 1);
});

test('asking for more than is left gives only what is left', function () {
    $this->weekmenu->update(['quantity' => 2]);

    checkout($this, [cartLine($this->weekmenu, 5)])->assertRedirect('/orders');

    expect(Order::first()->quantity)->toBe(2)
        ->and($this->weekmenu->fresh()->quantity)->toBe(0);
});

test('a sold out line is skipped but the rest of the cart still goes through', function () {
    $soldOut = weekmenuWithStock(0);

    checkout($this, [cartLine($soldOut, 2), cartLine($this->weekmenu, 2)])->assertRedirect('/orders');

    expect(Order::count())->toBe(1)
        ->and(Order::first()->weekmenu_id)->toBe($this->weekmenu->id);
});

test('a cart where everything is sold out creates nothing', function () {
    $this->weekmenu->update(['quantity' => 0]);

    checkout($this, [cartLine($this->weekmenu, 1)])->assertSessionHas('error');

    expect(Order::count())->toBe(0);
    Mail::assertNothingSent();
});

test('ordering the same menu twice adds to the same order', function () {
    checkout($this, [cartLine($this->weekmenu, 2)]);
    checkout($this, [cartLine($this->weekmenu, 3)]);

    expect(Order::count())->toBe(1)
        ->and(Order::first()->quantity)->toBe(5)
        ->and($this->weekmenu->fresh()->quantity)->toBe(5);
});

test('the last portion goes to the first customer only', function () {
    $this->weekmenu->update(['quantity' => 1]);
    $second = client();

    checkout($this, [cartLine($this->weekmenu, 1)])->assertRedirect('/orders');
    $this->actingAs($second);
    checkout($this, [cartLine($this->weekmenu, 1)])->assertSessionHas('error');

    expect(Order::sum('quantity'))->toBe(1)
        ->and(Order::first()->user_id)->toBe($this->customer->id)
        ->and($this->weekmenu->fresh()->quantity)->toBe(0);
});

test('an empty cart is rejected', function () {
    checkout($this, [])->assertSessionHasErrors('orders');
});

test('quantities must be at least one', function () {
    checkout($this, [cartLine($this->weekmenu, 0)])->assertSessionHasErrors('orders.0.quantity');
});

test('the pickup point is saved on the order', function () {
    $group = Group::factory()->create();
    $pickup = PickupPoint::create(['group_id' => $group->id, 'name' => 'Front door']);

    checkout($this, [cartLine($this->weekmenu, 1)], ['pickup_point_id' => $pickup->id]);

    expect(Order::first()->pickup_point_id)->toBe($pickup->id);
});

test('a valid discount code is applied and recorded', function () {
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);

    checkout($this, [cartLine($this->weekmenu, 2)], ['discount_code' => 'TEN'])->assertRedirect('/orders');

    expect(Order::first()->discount_amount)->toBe(2.0)
        ->and(DiscountRedemption::count())->toBe(1)
        ->and(DiscountRedemption::first()->discount_amount)->toEqual(2.0);
});

test('an unknown discount code stops the whole checkout', function () {
    checkout($this, [cartLine($this->weekmenu, 2)], ['discount_code' => 'NOPE'])
        ->assertSessionHasErrors('discount_code');

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10);
});

test('an ineligible discount code stops the whole checkout', function () {
    Discount::create([
        'code' => 'BIG', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general',
        'min_order_total' => 100, 'active' => true,
    ]);

    checkout($this, [cartLine($this->weekmenu, 2)], ['discount_code' => 'BIG'])
        ->assertSessionHasErrors('discount_code');

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10)
        ->and(DiscountRedemption::count())->toBe(0);
});

test('a discount code over its usage limit is refused', function () {
    $discount = Discount::create([
        'code' => 'ONCE', 'type' => 'fixed', 'value' => 1, 'target_type' => 'general',
        'usage_limit' => 1, 'active' => true,
    ]);

    checkout($this, [cartLine($this->weekmenu, 1)], ['discount_code' => 'ONCE'])->assertRedirect('/orders');
    checkout($this, [cartLine($this->weekmenu, 1)], ['discount_code' => 'ONCE'])
        ->assertSessionHasErrors('discount_code');

    expect(DiscountRedemption::where('discount_id', $discount->id)->count())->toBe(1);
});

test('spending enough with the right member ID adds a free gift line', function () {
    $reward = Menu::factory()->create(['price' => 4]);
    Gift::create(['threshold_amount' => 25, 'reward_menu_id' => $reward->id, 'active' => true]);

    // 6 x 10 = 60, which is two full steps of 25
    checkout($this, [cartLine($this->weekmenu, 6)], ['gift_code' => 'M-1001'])->assertRedirect('/orders');

    $gift = Order::whereNull('weekmenu_id')->first();

    expect($gift->menu_id)->toBe($reward->id)
        ->and($gift->quantity)->toBe(2)
        ->and($gift->special_price)->toBe(0.0)
        ->and(GiftRedemption::count())->toBe(1);
});

test('a wrong member ID stops the whole checkout', function () {
    $reward = Menu::factory()->create();
    Gift::create(['threshold_amount' => 25, 'reward_menu_id' => $reward->id, 'active' => true]);

    checkout($this, [cartLine($this->weekmenu, 6)], ['gift_code' => 'WRONG'])
        ->assertSessionHasErrors('gift_code');

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10)
        ->and(GiftRedemption::count())->toBe(0);
});

test('discount validation answers with json for the cart page', function () {
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);

    $this->postJson(route('validate_discount'), [
        'discount_code' => 'TEN', 'orders' => [cartLine($this->weekmenu, 2)],
    ])->assertOk()->assertJson(['valid' => true, 'discount_amount' => 2]);

    $this->postJson(route('validate_discount'), [
        'discount_code' => 'NOPE', 'orders' => [cartLine($this->weekmenu, 2)],
    ])->assertOk()->assertJson(['valid' => false]);
});

test('validating a discount does not use it up', function () {
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);

    $this->postJson(route('validate_discount'), [
        'discount_code' => 'TEN', 'orders' => [cartLine($this->weekmenu, 2)],
    ]);

    expect(DiscountRedemption::count())->toBe(0)
        ->and(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10);
});
