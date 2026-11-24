<?php

use App\Models\Menu;
use App\Models\Order;
use App\Models\Weekmenu;

beforeEach(function () {
    $this->actingAs(admin());
    $this->customer = client();
    $this->weekmenu = weekmenuWithStock(10);
});

function postOrder($test, array $overrides = [])
{
    return $test->post(route('admin.orders_store'), $overrides + [
        'user_id' => $test->customer->id,
        'weekmenu_id' => $test->weekmenu->id,
        'quantity' => 3,
        'week' => $test->weekmenu->week,
        'year' => $test->weekmenu->year,
    ]);
}

test('placing an order takes portions from the stock', function () {
    postOrder($this)->assertRedirect()->assertSessionHasNoErrors();

    expect(Order::count())->toBe(1)
        ->and(Order::first()->quantity)->toBe(3)
        ->and($this->weekmenu->fresh()->quantity)->toBe(7);
});

test('ordering more than the stock is rejected and changes nothing', function () {
    postOrder($this, ['quantity' => 11])->assertSessionHasErrors('quantity');

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10);
});

test('the last portions can be ordered but not one more', function () {
    postOrder($this, ['quantity' => 10])->assertRedirect()->assertSessionHasNoErrors();
    postOrder($this, ['quantity' => 1])->assertSessionHasErrors('quantity');

    expect($this->weekmenu->fresh()->quantity)->toBe(0)
        ->and(Order::sum('quantity'))->toBe(10);
});

test('ordering the same menu again adds to the existing order', function () {
    postOrder($this, ['quantity' => 2]);
    postOrder($this, ['quantity' => 3, 'notes' => 'no onions']);

    expect(Order::count())->toBe(1)
        ->and(Order::first()->quantity)->toBe(5)
        ->and(Order::first()->notes)->toBe('no onions')
        ->and($this->weekmenu->fresh()->quantity)->toBe(5);
});

test('notes are optional', function () {
    postOrder($this)->assertRedirect()->assertSessionHasNoErrors();

    expect(Order::first()->notes)->toBeNull();
});

test('ordering a menu with no weekmenu yet creates one without negative stock', function () {
    $menu = Menu::factory()->create();

    $this->post(route('admin.orders_store'), [
        'user_id' => $this->customer->id,
        'menu_id' => $menu->id,
        'quantity' => 4,
        'week' => 30,
        'year' => 2026,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $created = Weekmenu::where('menu_id', $menu->id)->first();

    expect($created)->not->toBeNull()
        ->and($created->quantity)->toBe(0)
        ->and(Order::where('weekmenu_id', $created->id)->sum('quantity'))->toBe(4);
});

test('an order needs a menu', function () {
    $this->post(route('admin.orders_store'), [
        'user_id' => $this->customer->id,
        'quantity' => 1,
        'week' => 30,
        'year' => 2026,
    ])->assertSessionHasErrors('weekmenu_id');
});

test('raising an order takes more from the stock and lowering gives it back', function () {
    postOrder($this, ['quantity' => 4]);
    $order = Order::first();

    $this->put(route('admin.orders_update', $order), ['quantity' => 6])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->weekmenu->fresh()->quantity)->toBe(4);

    $this->put(route('admin.orders_update', $order), ['quantity' => 1])->assertRedirect()->assertSessionHasNoErrors();
    expect($this->weekmenu->fresh()->quantity)->toBe(9)
        ->and($order->fresh()->quantity)->toBe(1);
});

test('raising an order beyond the stock is rejected and keeps everything as it was', function () {
    postOrder($this, ['quantity' => 4]);
    $order = Order::first();

    $this->put(route('admin.orders_update', $order), ['quantity' => 11])->assertSessionHasErrors('quantity');

    expect($order->fresh()->quantity)->toBe(4)
        ->and($this->weekmenu->fresh()->quantity)->toBe(6);
});

test('updating notes and special price does not touch the stock', function () {
    postOrder($this, ['quantity' => 4]);
    $order = Order::first();

    $this->put(route('admin.orders_update', $order), [
        'quantity' => 4, 'special_price' => 7.5, 'notes' => 'extra spicy',
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($order->fresh()->special_price)->toBe(7.5)
        ->and($order->fresh()->notes)->toBe('extra spicy')
        ->and($this->weekmenu->fresh()->quantity)->toBe(6);
});

test('setting the quantity to zero removes the order and gives the stock back', function () {
    postOrder($this, ['quantity' => 4]);

    $this->put(route('admin.orders_update', Order::first()), ['quantity' => 0]);

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10);
});

test('deleting an order gives the stock back', function () {
    postOrder($this, ['quantity' => 4]);

    $this->delete(route('admin.orders_destroy', Order::first()));

    expect(Order::count())->toBe(0)
        ->and($this->weekmenu->fresh()->quantity)->toBe(10);
});

test('deleting a missing order is a 404', function () {
    $this->delete(route('admin.orders_destroy', 9999))->assertNotFound();
});

test('moving a week of orders to another client does not change the stock', function () {
    postOrder($this, ['quantity' => 4]);
    $other = client();

    $this->post(route('admin.orders_change_client', $this->customer), [
        'new_user_id' => $other->id, 'action' => 'move',
        'week' => $this->weekmenu->week, 'year' => $this->weekmenu->year,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Order::first()->user_id)->toBe($other->id)
        ->and(Order::count())->toBe(1)
        ->and($this->weekmenu->fresh()->quantity)->toBe(6);
});

test('copying a week of orders keeps the original', function () {
    postOrder($this, ['quantity' => 4]);
    $other = client();

    $this->post(route('admin.orders_change_client', $this->customer), [
        'new_user_id' => $other->id, 'action' => 'copy',
        'week' => $this->weekmenu->week, 'year' => $this->weekmenu->year,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Order::count())->toBe(2)
        ->and(Order::where('user_id', $this->customer->id)->count())->toBe(1)
        ->and(Order::where('user_id', $other->id)->count())->toBe(1);
});

test('a client can not be moved onto themselves', function () {
    $this->post(route('admin.orders_change_client', $this->customer), [
        'new_user_id' => $this->customer->id, 'action' => 'move', 'week' => 1, 'year' => 2026,
    ])->assertSessionHasErrors('new_user_id');
});
