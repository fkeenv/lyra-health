<?php

use function Pest\Laravel\{get};

it('can visit the welcome page', function () {
    $response = get('/');

    $response
        ->assertOk()
        ->assertSee('Welcome to Vital Signs Tracker')
        ->assertSee('Track your health metrics');
});

it('displays the navigation', function () {
    $response = get('/');

    $response
        ->assertOk()
        ->assertSee('Vital Signs Tracker');
});

it('shows get started and learn more buttons', function () {
    $response = get('/');

    $response
        ->assertOk()
        ->assertSee('Get Started')
        ->assertSee('Learn More');
});