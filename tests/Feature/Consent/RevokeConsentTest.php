<?php

declare(strict_types=1);

use App\Models\MedicalProfessional;
use App\Models\PatientProviderConsent;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->medicalProfessional = MedicalProfessional::factory()->create();

    $this->consent = PatientProviderConsent::factory()->create([
        'user_id' => $this->user->id,
        'medical_professional_id' => $this->medicalProfessional->id,
        'is_active' => true,
        'revoked_at' => null,
    ]);
});

test('user can revoke their own consent', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/consent/{$this->consent->id}/revoke");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'is_active',
                'revoked_at',
                'updated_at',
            ],
        ]);
});

test('can revoke already revoked consent', function () {
    $this->consent->update([
        'is_active' => false,
        'revoked_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/consent/{$this->consent->id}/revoke");

    $response->assertOk();
});

test('cannot revoke another users consent', function () {
    $otherUser = User::factory()->create();
    $otherConsent = PatientProviderConsent::factory()->create([
        'user_id' => $otherUser->id,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/consent/{$otherConsent->id}/revoke");

    $response->assertForbidden();
});

test('returns not found for non-existent consent', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/consent/999999/revoke');

    $response->assertNotFound();
});

test('requires authentication to revoke consent', function () {
    $response = $this->postJson("/api/consent/{$this->consent->id}/revoke");

    $response->assertUnauthorized();
});
