<?php

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('admins can visit the dashboard', function () {
    $this->actingAs(admin())->get(route('admin.dashboard'))->assertOk();
});

test('clients can not visit the admin dashboard', function () {
    $this->actingAs(client())->get(route('admin.dashboard'))->assertForbidden();
});
