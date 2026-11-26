<?php

use App\Models\User;

/*
 * The users table has a "username" column and no "email" column; the model
 * presents the username as the email so login and password reset keep working.
 */

test('email reads the username', function () {
    $user = new User(['username' => 'mama@example.com']);

    expect($user->email)->toBe('mama@example.com');
});

test('setting the email writes the username', function () {
    $user = new User();
    $user->email = 'liu@example.com';

    expect($user->getAttributes())->toHaveKey('username', 'liu@example.com')
        ->and($user->getAttributes())->not->toHaveKey('email');
});

test('password reset and verification use the username', function () {
    $user = new User(['username' => 'mama@example.com']);

    expect($user->getEmailForPasswordReset())->toBe('mama@example.com')
        ->and($user->getEmailForVerification())->toBe('mama@example.com');
});

test('a missing username gives a null email', function () {
    expect((new User())->email)->toBeNull();
});
