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

test('the order of the lines in the cart does not change the result', function () {
    $other = weekmenuWithStock(5);

    // Locks are taken in id order; sqlite can't show a deadlock, only that results match
    checkout($this, [cartLine($other, 2), cartLine($this->weekmenu, 3)])->assertRedirect('/orders');

    expect(Order::count())->toBe(2)
        ->and($this->weekmenu->fresh()->quantity)->toBe(7)
        ->and($other->fresh()->quantity)->toBe(3);
});

test('the same weekmenu twice in one cart is capped by the stock that is really left', function () {
    $this->weekmenu->update(['quantity' => 4]);

    checkout($this, [cartLine($this->weekmenu, 3), cartLine($this->weekmenu, 3)])->assertRedirect('/orders');

    expect(Order::count())->toBe(1)
        ->and(Order::first()->quantity)->toBe(4)
        ->and($this->weekmenu->fresh()->quantity)->toBe(0);
});

test('the same weekmenu twice in one cart gets the full discount on one order', function () {
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);

    checkout($this, [cartLine($this->weekmenu, 2, 'first'), cartLine($this->weekmenu, 3, 'second')], ['discount_code' => 'TEN'])
        ->assertRedirect('/orders')
        ->assertSessionHas('orderCount', 1);

    // 5 x 10.00 at 10% = 5.00
    expect(Order::count())->toBe(1)
        ->and(Order::first()->quantity)->toBe(5)
        ->and(Order::first()->notes)->toBe('second')
        ->and(Order::first()->discount_amount)->toBe(5.0)
        ->and(DiscountRedemption::first()->discount_amount)->toEqual(5.0);
});

test('sending the same checkout twice orders only once', function () {
    $lines = [cartLine($this->weekmenu, 3)];

    checkout($this, $lines, ['idempotency_key' => 'abc-123'])->assertRedirect('/orders')->assertSessionHas('orderCount', 1);
    checkout($this, $lines, ['idempotency_key' => 'abc-123'])->assertRedirect('/orders')->assertSessionHas('orderCount', 1);

    expect(Order::first()->quantity)->toBe(3)
        ->and($this->weekmenu->fresh()->quantity)->toBe(7);
    Mail::assertSent(OrderConfirmation::class, 1);
});

test('a new token is a new checkout', function () {
    checkout($this, [cartLine($this->weekmenu, 3)], ['idempotency_key' => 'one']);
    checkout($this, [cartLine($this->weekmenu, 2)], ['idempotency_key' => 'two']);

    expect(Order::first()->quantity)->toBe(5);
});

test('a failed checkout can be retried with the same token', function () {
    checkout($this, [cartLine($this->weekmenu, 1)], ['idempotency_key' => 'retry', 'discount_code' => 'NOPE'])
        ->assertSessionHasErrors('discount_code');

    checkout($this, [cartLine($this->weekmenu, 1)], ['idempotency_key' => 'retry'])->assertRedirect('/orders');

    expect(Order::count())->toBe(1);
});

test('a second checkout in the same week adds to the discount on an existing line', function () {
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);

    checkout($this, [cartLine($this->weekmenu, 2)], ['discount_code' => 'TEN']);
    checkout($this, [cartLine($this->weekmenu, 3)], ['discount_code' => 'TEN']);

    // 2.00 + 3.00
    expect(Order::count())->toBe(1)
        ->and(Order::first()->quantity)->toBe(5)
        ->and(Order::first()->discount_amount)->toEqual(5.0);
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
    $this->customer->update(['group_id' => $group->id]);
    $pickup = PickupPoint::create(['group_id' => $group->id, 'name' => 'Front door']);

    checkout($this, [cartLine($this->weekmenu, 1)], ['pickup_point_id' => $pickup->id]);

    expect(Order::first()->pickup_point_id)->toBe($pickup->id);
});

test('a pickup point of another group is refused', function () {
    $this->customer->update(['group_id' => Group::factory()->create()->id]);
    $foreign = PickupPoint::create(['group_id' => Group::factory()->create()->id, 'name' => 'Elsewhere']);

    checkout($this, [cartLine($this->weekmenu, 1)], ['pickup_point_id' => $foreign->id])
        ->assertSessionHasErrors('pickup_point_id');

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10);
});

test('a client without a group cannot pick a pickup point', function () {
    $pickup = PickupPoint::create(['group_id' => Group::factory()->create()->id, 'name' => 'Front door']);

    checkout($this, [cartLine($this->weekmenu, 1)], ['pickup_point_id' => $pickup->id])
        ->assertSessionHasErrors('pickup_point_id');

    expect(Order::count())->toBe(0);
});

test('a weekmenu of another group cannot be ordered', function () {
    $this->customer->update(['group_id' => Group::factory()->create()->id]);
    $foreign = weekmenuWithStock(5, ['group_id' => Group::factory()->create()->id]);

    checkout($this, [cartLine($foreign, 2)])->assertSessionHas('error');

    expect(Order::count())->toBe(0)
        ->and($foreign->fresh()->quantity)->toBe(5);
});

test('own group and group-less weekmenus can be ordered, a foreign one in the same cart is dropped', function () {
    $group = Group::factory()->create();
    $this->customer->update(['group_id' => $group->id]);
    $own = weekmenuWithStock(5, ['group_id' => $group->id]);
    $foreign = weekmenuWithStock(5, ['group_id' => Group::factory()->create()->id]);

    checkout($this, [cartLine($own, 1), cartLine($this->weekmenu, 1), cartLine($foreign, 1)])->assertRedirect('/orders');

    expect(Order::pluck('weekmenu_id')->sort()->values()->all())->toBe(collect([$own->id, $this->weekmenu->id])->sort()->values()->all())
        ->and($foreign->fresh()->quantity)->toBe(5);
});

test('a weekmenu from a past week cannot be ordered', function () {
    $past = now()->subWeek();
    $old = weekmenuWithStock(5, ['week' => $past->isoWeek, 'year' => $past->isoWeekYear]);

    checkout($this, [cartLine($old, 1)])->assertSessionHas('error');

    expect(Order::count())->toBe(0)
        ->and($old->fresh()->quantity)->toBe(5);
});

test('the current week and a future week can be ordered', function () {
    $next = now()->addWeek();
    $future = weekmenuWithStock(5, ['week' => $next->isoWeek, 'year' => $next->isoWeekYear]);

    checkout($this, [cartLine($this->weekmenu, 1), cartLine($future, 1)])->assertRedirect('/orders');

    expect(Order::count())->toBe(2);
});

test('a line from another group does not count towards a discount', function () {
    $this->customer->update(['group_id' => Group::factory()->create()->id]);
    $foreign = weekmenuWithStock(5, ['group_id' => Group::factory()->create()->id, 'menu_id' => Menu::factory()->create(['price' => 100])->id]);
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);

    checkout($this, [cartLine($this->weekmenu, 1), cartLine($foreign, 1)], ['discount_code' => 'TEN'])->assertRedirect('/orders');

    // 10% of the own 10.00 line only
    expect(Order::count())->toBe(1)
        ->and(Order::first()->discount_amount)->toBe(1.0);
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

test('validation of a cart with nothing orderable says so instead of checking the code', function () {
    $this->customer->update(['group_id' => Group::factory()->create()->id]);
    $foreign = weekmenuWithStock(5, ['group_id' => Group::factory()->create()->id]);
    Discount::create(['code' => 'TEN', 'type' => 'percentage', 'value' => 10, 'target_type' => 'general', 'active' => true]);
    Gift::create(['threshold_amount' => 25, 'reward_menu_id' => Menu::factory()->create()->id, 'active' => true]);

    $this->postJson(route('validate_discount'), ['discount_code' => 'TEN', 'orders' => [cartLine($foreign, 1)]])
        ->assertOk()->assertJson(['valid' => false, 'message' => 'None of the selected items are available.']);

    $this->postJson(route('validate_gift'), ['gift_code' => 'M-1001', 'orders' => [cartLine($foreign, 1)]])
        ->assertOk()->assertJson(['valid' => false, 'message' => 'None of the selected items are available.']);
});

test('code validation is throttled so codes cannot be guessed', function () {
    $payload = ['discount_code' => 'NOPE', 'orders' => [cartLine($this->weekmenu, 1)]];

    foreach (range(1, 20) as $i) {
        $this->postJson(route('validate_discount'), $payload)->assertOk();
    }

    $this->postJson(route('validate_discount'), $payload)->assertStatus(429);
});

test('a nonsense week or year in the url does not crash the home page', function () {
    $this->get(route('home', ['week' => 'abc', 'year' => 'xyz']))->assertOk();
    $this->get(route('home', ['week' => 99, 'year' => 1]))->assertOk();
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
