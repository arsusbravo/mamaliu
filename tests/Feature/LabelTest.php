<?php

use App\Models\Label;
use App\Models\Menu;
use App\Support\LabelSheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => $this->actingAs(admin()));

function labelData(array $overrides = []): array
{
    return $overrides + ['name' => 'Spicy', 'text' => 'Contains peanuts', 'show_logo' => true, 'text_color' => '#112233'];
}

test('a label can be created', function () {
    $this->post(route('admin.labels_store'), labelData())
        ->assertRedirect(route('admin.labels_index'))
        ->assertSessionHas('success');

    $label = Label::first();

    expect($label->name)->toBe('Spicy')
        ->and($label->text)->toBe('Contains peanuts')
        ->and($label->show_logo)->toBeTrue()
        ->and($label->text_color)->toBe('#112233');
});

test('label input is validated', function (array $bad, string $field) {
    $this->post(route('admin.labels_store'), labelData($bad))->assertSessionHasErrors($field);

    expect(Label::count())->toBe(0);
})->with([
    'name is required' => [['name' => ''], 'name'],
    'colour must be hex' => [['text_color' => 'red'], 'text_color'],
    'colour needs six digits' => [['text_color' => '#fff'], 'text_color'],
    'logo flag is required' => [['show_logo' => null], 'show_logo'],
    'background must be an image type' => [['background' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')], 'background'],
]);

test('the colour is stored in lower case', function () {
    $this->post(route('admin.labels_store'), labelData(['text_color' => '#AABBCC']));

    expect(Label::first()->text_color)->toBe('#aabbcc');
});

test('a label can be updated and deleted', function () {
    $label = Label::create(labelData());

    $this->put(route('admin.labels_update', $label), labelData(['name' => 'Mild']))
        ->assertRedirect(route('admin.labels_index'));
    expect($label->fresh()->name)->toBe('Mild');

    $this->delete(route('admin.labels_destroy', $label))->assertRedirect(route('admin.labels_index'));
    expect(Label::count())->toBe(0);
});

test('an uploaded background is saved, served and removed with the label', function () {
    $this->post(route('admin.labels_store'), labelData([
        'background' => UploadedFile::fake()->image('bg.jpg', 800, 400),
    ]))->assertSessionHasNoErrors();

    $label = Label::first();

    expect($label->has_background)->toBeTrue();
    Storage::disk('public')->assertExists($label->backgroundPath());
    $this->get(route('label.background', $label))->assertOk();

    $this->delete(route('admin.labels_destroy', $label));
    Storage::disk('public')->assertMissing($label->backgroundPath());
});

test('the background can be removed while editing', function () {
    $this->post(route('admin.labels_store'), labelData(['background' => UploadedFile::fake()->image('bg.jpg', 300, 300)]));
    $label = Label::first();

    $this->put(route('admin.labels_update', $label), labelData(['delete_background' => true]));

    expect($label->fresh()->has_background)->toBeFalse();
});

test('the background route is a 404 when there is no background', function () {
    $label = Label::create(labelData());

    $this->get(route('label.background', $label))->assertNotFound();
});

test('a label prints as a pdf in any known format', function (string $format) {
    $label = Label::create(labelData());

    $response = $this->get(route('admin.labels_pdf', [$label, 'format' => $format]))->assertOk();

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
})->with(['pcl12', '2x2', '2x4', '3x8', '2x7']);

test('the format list used by these tests matches the config', function () {
    expect(array_keys(config('labels.formats')))->toBe(['pcl12', '2x2', '2x4', '3x8', '2x7']);
});

test('printing needs a known format', function () {
    $label = Label::create(labelData());

    $this->get(route('admin.labels_pdf', [$label, 'format' => 'bogus']))->assertSessionHasErrors('format');
    $this->get(route('admin.labels_pdf', $label))->assertSessionHasErrors('format');
});

test('a label with a background prints as a pdf', function () {
    $this->post(route('admin.labels_store'), labelData(['background' => UploadedFile::fake()->image('bg.jpg', 800, 400)]));

    $response = $this->get(route('admin.labels_pdf', [Label::first(), 'format' => 'pcl12']))->assertOk();

    expect(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('a menu prints its own name, or the text typed in the dialog', function () {
    $menu = Menu::factory()->create(['label' => 'Dumplings']);

    $plain = $this->get(route('admin.menus_labels', [$menu, 'format' => 'pcl12']))->assertOk();
    $custom = $this->get(route('admin.menus_labels', [$menu, 'format' => 'pcl12', 'text' => 'Fresh today']))->assertOk();

    expect(substr($plain->getContent(), 0, 4))->toBe('%PDF')
        ->and($plain->headers->get('Content-Disposition'))->toContain('label-dumplings.pdf')
        ->and($custom->headers->get('Content-Disposition'))->toContain('label-fresh-today.pdf');
});

test('menu labels need a known format', function () {
    $menu = Menu::factory()->create();

    $this->get(route('admin.menus_labels', [$menu, 'format' => 'bogus']))->assertSessionHasErrors('format');
});

test('LabelSheet builds a pdf for every preset', function (string $format) {
    $pdf = LabelSheet::pdf(config('labels.formats.' . $format), 'Test label', 'some text');

    expect(substr($pdf, 0, 4))->toBe('%PDF');
})->with(['pcl12', '2x2', '2x4', '3x8', '2x7']);

test('clients can not use the label maker', function () {
    $this->actingAs(client());

    $this->post(route('admin.labels_store'), labelData())->assertForbidden();
    expect(Label::count())->toBe(0);
});
