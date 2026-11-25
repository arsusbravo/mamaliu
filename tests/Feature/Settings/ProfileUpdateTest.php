<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

// Note: the form validates the login name as a real email address, so a user whose
// username is not an email (e.g. "mama") can not save the profile page.
test('profile name can be updated', function () {
    $user = User::factory()->create(['username' => 'mama@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'mama@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
});

test('login name can be changed from the profile page', function () {
    $user = User::factory()->create(['username' => 'mama@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com'])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->username)->toBe('new@example.com');
});

test('keeping the same login name is allowed', function () {
    $user = User::factory()->create(['username' => 'mama@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Same', 'email' => 'mama@example.com'])
        ->assertSessionHasNoErrors();
});

test('a login name that belongs to someone else is rejected', function () {
    User::factory()->create(['username' => 'taken@example.com']);
    $user = User::factory()->create(['username' => 'mama@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test', 'email' => 'taken@example.com'])
        ->assertSessionHasErrors('email');

    expect($user->refresh()->username)->toBe('mama@example.com');
});

// The users table has no "email" column. On SQLite an unknown quoted column silently
// compares as text, so a query on it passes in tests but crashes on MySQL. Check the
// rule itself so that can not slip through again.
test('the uniqueness check targets the username column', function () {
    $request = new App\Http\Requests\Settings\ProfileUpdateRequest();
    $request->setUserResolver(fn () => User::factory()->create());

    $unique = collect($request->rules()['email'])->first(fn ($rule) => str_starts_with((string) $rule, 'unique:'));

    expect((string) $unique)->toStartWith('unique:users,username,');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});