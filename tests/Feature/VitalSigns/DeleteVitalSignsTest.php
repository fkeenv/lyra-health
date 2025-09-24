<?php

declare(strict_types=1);

use App\Models\User;
use App\Models\VitalSignsRecord;
use App\Models\VitalSignType;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->vitalSignType = VitalSignType::factory()->create();
    $this->vitalSignsRecord = VitalSignsRecord::factory()->create([
        'user_id' => $this->user->id,
        'vital_sign_type_id' => $this->vitalSignType->id,
    ]);
});

test('can delete own vital signs record', function () {
    $response = $this->actingAs($this->user)
        ->deleteJson("/api/vital-signs/{$this->vitalSignsRecord->id}");

    $response->assertNoContent();
});

test('cannot delete another users vital signs record', function () {
    $otherUser = User::factory()->create();
    $otherRecord = VitalSignsRecord::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->deleteJson("/api/vital-signs/{$otherRecord->id}");

    $response->assertForbidden();
});

test('returns not found for non-existent record', function () {
    $response = $this->actingAs($this->user)
        ->deleteJson('/api/vital-signs/non-existent-id');

    $response->assertNotFound();
});

test('requires authentication to delete vital signs record', function () {
    $response = $this->deleteJson("/api/vital-signs/{$this->vitalSignsRecord->id}");

    $response->assertUnauthorized();
});
