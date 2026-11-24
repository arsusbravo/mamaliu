<?php

use App\Models\Group;
use App\Models\Menu;
use App\Models\Order;
use Illuminate\Support\Carbon;

beforeEach(fn () => $this->actingAs(admin()));

afterEach(fn () => Carbon::setTestNow());

/** The sorted names/labels shown on the first page of a list. */
function listed($test, string $route, array $query, string $key, string $prop): array
{
    $data = $test->get(route($route, $query))->assertOk()->viewData('page')['props'][$prop]['data'];

    return collect($data)->pluck($key)->all();
}

test('menus can be sorted by label in both directions', function () {
    Menu::factory()->create(['label' => 'B']);
    Menu::factory()->create(['label' => 'A']);
    Menu::factory()->create(['label' => 'C']);

    expect(listed($this, 'admin.menus_index', ['sort' => 'label', 'direction' => 'asc'], 'label', 'menus'))->toBe(['A', 'B', 'C'])
        ->and(listed($this, 'admin.menus_index', ['sort' => 'label', 'direction' => 'desc'], 'label', 'menus'))->toBe(['C', 'B', 'A']);
});

test('groups can be sorted by name', function () {
    Group::factory()->create(['name' => 'Beta']);
    Group::factory()->create(['name' => 'Alpha']);

    expect(listed($this, 'admin.groups_index', ['sort' => 'name', 'direction' => 'asc'], 'name', 'groups'))->toBe(['Alpha', 'Beta']);
});

test('clients can be sorted by name', function () {
    client(['name' => 'Zed']);
    client(['name' => 'Amy']);

    $names = collect($this->get(route('admin.clients_index', ['sort' => 'name', 'direction' => 'asc']))
        ->assertOk()->viewData('page')['props']['clients']['data'])->pluck('name')->all();

    // The list also shows the logged in admin (it has no user type filter), so only compare ours
    expect(array_values(array_intersect($names, ['Amy', 'Zed'])))->toBe(['Amy', 'Zed']);
});

test('a made-up sort column or direction falls back instead of failing', function (string $route) {
    Menu::factory()->create();

    $this->get(route($route, ['sort' => 'password; drop table users', 'direction' => 'sideways']))->assertOk();
    $this->get(route($route, ['sort' => 'no_such_column']))->assertOk();
})->with(['admin.menus_index', 'admin.groups_index', 'admin.clients_index']);

test('the fallback sort is reported back to the page', function () {
    $filters = $this->get(route('admin.menus_index', ['sort' => 'bogus', 'direction' => 'sideways']))
        ->viewData('page')['props']['filters'];

    expect($filters['sort'])->toBe('created_at')->and($filters['direction'])->toBe('desc');
});

test('the orders page opens on the current ISO week', function (string $today, int $week, int $year) {
    Carbon::setTestNow($today);

    $props = $this->get(route('admin.orders_index'))->assertOk()->viewData('page')['props'];

    expect($props['currentWeek'])->toBe($week)->and($props['currentYear'])->toBe($year);
})->with([
    'normal day' => ['2026-06-10', 24, 2026],
    // 31 Dec 2026 is a Thursday: ISO week 53 of 2026 (calendar week would say 1)
    'last day of a 53 week year' => ['2026-12-31', 53, 2026],
    // 1 Jan 2027 is a Friday and still belongs to ISO week 53 of 2026
    'new year inside the old ISO year' => ['2027-01-01', 53, 2026],
    // 29 Dec 2025 is a Monday: already ISO week 1 of 2026
    'new year inside the next ISO year' => ['2025-12-29', 1, 2026],
]);

test('the weekmenu page opens on the current ISO week', function () {
    Carbon::setTestNow('2026-12-31');

    $props = $this->get(route('admin.weekmenus_index'))->assertOk()->viewData('page')['props'];

    expect($props['currentWeek'])->toBe(53)->and($props['currentYear'])->toBe(2026);
});

test('the orders page only lists orders of the chosen week', function () {
    $thisWeek = weekmenuWithStock(5, ['week' => 10, 'year' => 2026]);
    $otherWeek = weekmenuWithStock(5, ['week' => 11, 'year' => 2026]);
    Order::factory()->create(['user_id' => client(['name' => 'Week Ten'])->id, 'weekmenu_id' => $thisWeek->id, 'week' => 10, 'year' => 2026]);
    Order::factory()->create(['user_id' => client(['name' => 'Week Eleven'])->id, 'weekmenu_id' => $otherWeek->id, 'week' => 11, 'year' => 2026]);

    $grouped = $this->get(route('admin.orders_index', ['week' => 10, 'year' => 2026]))->viewData('page')['props']['groupedOrders'];

    expect(collect($grouped)->pluck('user.name')->all())->toBe(['Week Ten']);
});
