<?php

use App\Models\User;
use App\Models\VitalSignType;

test('dashboard route returns 200 for authenticated user', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertStatus(200);
    $response->assertSee('<!DOCTYPE html>', false); // Don't escape HTML
});

test('vital signs create route returns 200 with data', function () {
    $user = User::factory()->create();

    // Ensure we have vital sign types
    VitalSignType::factory()->create([
        'name' => 'blood_pressure',
        'display_name' => 'Blood Pressure',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get('/vital-signs/create');

    $response->assertStatus(200);
    $response->assertSee('<!DOCTYPE html>', false);
});

test('trends route returns 200 with vital sign types', function () {
    $user = User::factory()->create();

    VitalSignType::factory()->create([
        'name' => 'heart_rate',
        'display_name' => 'Heart Rate',
        'is_active' => true,
    ]);

    $response = $this->actingAs($user)->get('/vital-signs/trends');

    $response->assertStatus(200);
    $response->assertSee('<!DOCTYPE html>', false);
});

test('recommendations route returns 200', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/recommendations');

    $response->assertStatus(200);
    $response->assertSee('<!DOCTYPE html>', false);
});

test('consent route returns 200', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/consent');

    $response->assertStatus(200);
    $response->assertSee('<!DOCTYPE html>', false);
});
