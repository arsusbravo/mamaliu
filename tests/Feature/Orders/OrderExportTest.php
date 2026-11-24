<?php

use App\Models\Menu;
use App\Models\Order;
use PhpOffice\PhpSpreadsheet\IOFactory;

beforeEach(function () {
    $this->actingAs(admin());

    $this->pasta = Menu::factory()->create(['label' => 'Pasta', 'price' => 10]);
    $this->soup = Menu::factory()->create(['label' => 'Soup', 'price' => 4]);
    $this->pastaWeek = weekmenuWithStock(20, ['menu_id' => $this->pasta->id]);
    $this->soupWeek = weekmenuWithStock(20, ['menu_id' => $this->soup->id]);

    $this->anna = client(['name' => 'Anna']);
    $this->ben = client(['name' => 'Ben']);

    $make = fn ($user, $weekmenu, $quantity) => Order::factory()->create([
        'user_id' => $user->id, 'weekmenu_id' => $weekmenu->id, 'quantity' => $quantity,
        'week' => $weekmenu->week, 'year' => $weekmenu->year,
    ]);

    $make($this->anna, $this->pastaWeek, 2);
    $make($this->anna, $this->soupWeek, 1);
    $make($this->ben, $this->pastaWeek, 3);
});

/** Download the export and return its rows as a plain array. */
function exportedRows($test): array
{
    $response = $test->get(route('admin.orders_export', [
        'week' => $test->pastaWeek->week, 'year' => $test->pastaWeek->year,
    ]))->assertOk();

    $spreadsheet = IOFactory::load($response->baseResponse->getFile()->getPathname());

    return $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
}

test('the excel export lists each client with their quantities per menu', function () {
    $rows = exportedRows($this);

    expect($rows[0])->toBe(['Nr.', 'Name', 'Pasta', 'Soup', 'Total Qty', 'Total Price']);

    $anna = collect($rows)->firstWhere(1, 'Anna');
    $ben = collect($rows)->firstWhere(1, 'Ben');

    expect(array_slice($anna, 2, 3))->toEqual([2, 1, 3])
        ->and(array_slice($ben, 2, 3))->toEqual([3, null, 3]);
});

test('the TOTAL row has an empty cell under Name so totals line up with their columns', function () {
    $rows = exportedRows($this);
    $total = collect($rows)->firstWhere(0, 'TOTAL');

    expect($total)->not->toBeNull()
        ->and($total[1])->toBeNull()
        ->and($total[2])->toEqual(5)   // pasta: 2 + 3
        ->and($total[3])->toEqual(1)   // soup
        ->and($total[4])->toEqual(6);  // total quantity
});

test('prices in the export use the menu price', function () {
    $total = collect(exportedRows($this))->firstWhere(0, 'TOTAL');

    // 5 x 10 pasta + 1 x 4 soup
    expect((float) $total[5])->toBe(54.0);
});

test('a client with notes appears in the notes section', function () {
    Order::where('user_id', $this->anna->id)->first()->update(['notes' => 'allergic to nuts']);

    $rows = exportedRows($this);

    expect(collect($rows)->pluck(0))->toContain('NOTES')
        ->and(collect($rows)->contains(fn ($row) => $row[1] === 'allergic to nuts'))->toBeTrue();
});

test('the export only contains the requested week', function () {
    $other = weekmenuWithStock(5, ['week' => 3, 'year' => 2020]);
    Order::factory()->create([
        'user_id' => client(['name' => 'Old Order Person'])->id, 'weekmenu_id' => $other->id,
        'quantity' => 1, 'week' => 3, 'year' => 2020,
    ]);

    expect(collect(exportedRows($this))->pluck(1))->not->toContain('Old Order Person');
});

test('the pdf export is a pdf', function () {
    $response = $this->get(route('admin.orders_pdf', [
        'week' => $this->pastaWeek->week, 'year' => $this->pastaWeek->year,
    ]))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('clients can not download the exports', function () {
    $this->actingAs(client());

    $this->get(route('admin.orders_export'))->assertForbidden();
    $this->get(route('admin.orders_pdf'))->assertForbidden();
});
