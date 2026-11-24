<?php

use App\Models\User;

test('login screen can be rendered', function () {
    $this->get(route('login'))->assertOk();
});

test('users log in with their username', function () {
    $user = User::factory()->create(['username' => 'mama']);

    // Fortify posts the field as "email" but the app matches it against username
    $this->post(route('login.store'), ['email' => 'mama', 'password' => 'password']);

    $this->assertAuthenticatedAs($user);
});

test('users can not authenticate with invalid password', function () {
    User::factory()->create(['username' => 'mama']);

    $this->post(route('login.store'), ['email' => 'mama', 'password' => 'wrong-password']);

    $this->assertGuest();
});

test('unknown usernames can not authenticate', function () {
    $this->post(route('login.store'), ['email' => 'nobody', 'password' => 'password']);

    $this->assertGuest();
});

test('users can logout', function () {
    $response = $this->actingAs(User::factory()->create())->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});
