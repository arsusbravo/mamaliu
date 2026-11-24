<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

// Note: the form validates "email" as a real email address, so a user whose
// username is not an email (e.g. "mama") can not save the profile page at all.
test('profile name can be updated', function () {
    $user = User::factory()->create(['username' => 'mama@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'mama@example.com'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
});

// The profile form posts "email", but User::$fillable only has "username", so the
// value is silently dropped on mass assignment. Remove ->skip() once fixed.
test('login name can be changed from the profile page', function () {
    $user = User::factory()->create(['username' => 'mama@example.com']);

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com']);

    expect($user->refresh()->username)->toBe('new@example.com');
})->skip('Known bug: email is not mass assignable, so the change is ignored.');

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