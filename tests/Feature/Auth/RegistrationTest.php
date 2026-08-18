<?php

use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'bara12',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('home', absolute: false));
});

test('passwords shorter than six characters are rejected', function () {
    $this->post(route('register.store'), [
        'name' => 'Quick Bara',
        'email' => 'quick@example.com',
        'password' => 'bara1',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
});
