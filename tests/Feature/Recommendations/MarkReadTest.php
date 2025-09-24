<?php

declare(strict_types=1);

use App\Models\Recommendation;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->recommendation = Recommendation::factory()->create([
        'user_id' => $this->user->id,
        'read_at' => null,
    ]);
});

test('can mark recommendation as read', function () {
    $response = $this->actingAs($this->user)
        ->postJson("/api/recommendations/{$this->recommendation->id}/read");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'read_at',
                'updated_at',
            ],
        ]);
});

test('can mark already read recommendation as read again', function () {
    $alreadyReadRecommendation = Recommendation::factory()->create([
        'user_id' => $this->user->id,
        'read_at' => now(),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/recommendations/{$alreadyReadRecommendation->id}/read");

    $response->assertOk();
});

test('cannot mark another users recommendation as read', function () {
    $otherUser = User::factory()->create();
    $otherRecommendation = Recommendation::factory()->create([
        'user_id' => $otherUser->id,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/recommendations/{$otherRecommendation->id}/read");

    $response->assertForbidden();
});

test('returns not found for non-existent recommendation', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/recommendations/non-existent-id/read');

    $response->assertNotFound();
});

test('requires authentication to mark recommendation as read', function () {
    $response = $this->postJson("/api/recommendations/{$this->recommendation->id}/read");

    $response->assertUnauthorized();
});
