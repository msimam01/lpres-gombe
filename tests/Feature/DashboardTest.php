<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('administrators can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->admin()->create());

    $this->get('/dashboard')->assertOk();
});

test('ordinary authenticated users cannot visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertForbidden();
});
