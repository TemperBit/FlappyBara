<?php

use Inertia\Testing\AssertableInertia as Assert;

test('the game is available to guests', function () {
    $this->withoutVite();

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Game'));
});
