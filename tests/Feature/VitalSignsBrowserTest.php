<?php

use App\Models\User;
use App\Models\VitalSignType;

test('dashboard loads and displays correctly in browser', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Health Dashboard')
        ->assertSee('Record Vital Signs')
        ->assertSee('Quick Actions');
});

test('vital signs create form works in browser', function () {
    $user = User::factory()->create();

    // Create a vital sign type
    VitalSignType::factory()->create([
        'name' => 'blood_pressure',
        'display_name' => 'Blood Pressure',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    visit('/vital-signs/create')
        ->assertSee('Record Vital Signs')
        ->assertSee('Measurement Type')
        ->assertSee('Blood Pressure')
        ->assertSee('Save Measurement');
});

test('trends page loads and shows filters', function () {
    $user = User::factory()->create();

    VitalSignType::factory()->create([
        'name' => 'heart_rate',
        'display_name' => 'Heart Rate',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    visit('/vital-signs/trends')
        ->assertSee('Health Trends')
        ->assertSee('Filter & View Options')
        ->assertSee('Heart Rate');
});

test('can navigate between pages using header navigation', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    visit('/dashboard')
        ->assertSee('Lyra Health')
        ->click('Record')
        ->assertPathIs('/vital-signs/create')
        ->assertSee('Record Vital Signs')
        ->click('Dashboard')
        ->assertPathIs('/dashboard')
        ->assertSee('Health Dashboard');
});
