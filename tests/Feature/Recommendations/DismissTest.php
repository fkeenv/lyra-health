<?php

declare(strict_types=1);

use App\Models\Recommendation;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->recommendation = Recommendation::factory()->create([
        'user_id' => $this->user->id,
        'dismissed_at' => null,
    ]);
});

test('can dismiss recommendation', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/recommendations/{$this->recommendation->id}/dismiss");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'dismissed_at',
                'updated_at',
            ],
        ]);
});

test('can dismiss already dismissed recommendation', function () {
    $alreadyDismissedRecommendation = Recommendation::factory()->create([
        'user_id' => $this->user->id,
        'dismissed_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/recommendations/{$alreadyDismissedRecommendation->id}/dismiss");

    $response->assertOk();
});

test('cannot dismiss another users recommendation', function () {
    $otherUser = User::factory()->create();
    $otherRecommendation = Recommendation::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/recommendations/{$otherRecommendation->id}/dismiss");

    $response->assertForbidden();
});

test('returns not found for non-existent recommendation', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/recommendations/non-existent-id/dismiss');

    $response->assertNotFound();
});

test('requires authentication to dismiss recommendation', function () {
    $response = $this->postJson("/api/recommendations/{$this->recommendation->id}/dismiss");

    $response->assertUnauthorized();
});
