<?php

$adminPages = [
    'admin.dashboard', 'admin.orders_index', 'admin.menus_index', 'admin.clients_index',
    'admin.groups_index', 'admin.weekmenus_index', 'admin.discounts_index', 'admin.gifts_index',
    'admin.labels_index', 'admin.expenses_index', 'admin.invites_index', 'admin.emails_index',
];

test('guests are sent to the login page', function (string $route) {
    $this->get(route($route))->assertRedirect(route('login'));
})->with($adminPages);

test('clients can not open admin pages', function (string $route) {
    $this->actingAs(client())->get(route($route))->assertForbidden();
})->with($adminPages);

test('admins can open every admin page', function (string $route) {
    $this->actingAs(admin())->get(route($route))->assertOk();
})->with($adminPages);

test('clients can open the client pages', function () {
    $client = client();

    $this->actingAs($client)->get(route('home'))->assertOk();
    $this->actingAs($client)->get(route('user.orders_index'))->assertOk();
});

test('clients can not change orders through admin endpoints', function () {
    $order = App\Models\Order::factory()->create();

    $this->actingAs(client())->delete(route('admin.orders_destroy', $order))->assertForbidden();

    expect(App\Models\Order::count())->toBe(1);
});
