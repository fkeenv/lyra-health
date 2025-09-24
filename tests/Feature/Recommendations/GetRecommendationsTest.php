<?php

declare(strict_types=1);

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('can get user recommendations', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/recommendations');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'recommendation_type',
                    'title',
                    'message',
                    'severity',
                    'action_required',
                    'read_at',
                    'dismissed_at',
                    'expires_at',
                    'metadata',
                    'created_at',
                    'updated_at',
                    'vital_signs_record' => [
                        'id',
                        'value_primary',
                        'value_secondary',
                        'unit',
                        'measured_at',
                    ],
                ],
            ],
            'meta' => [
                'current_page',
                'last_page',
                'per_page',
                'total',
                'unread_count',
            ],
        ]);
});

test('can filter recommendations by type', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/recommendations?type=warning');

    $response->assertOk();
});

test('can filter recommendations by severity', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/recommendations?severity=high');

    $response->assertOk();
});

test('can filter unread recommendations', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/recommendations?unread=true');

    $response->assertOk();
});

test('can filter active recommendations only', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/recommendations?active=true');

    $response->assertOk();
});

test('validates filter parameters', function () {
    $response = $this->actingAs($this->user)
        ->getJson('/api/recommendations?type=invalid&severity=invalid');

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'severity']);
});

test('requires authentication to get recommendations', function () {
    $response = $this->getJson('/api/recommendations');

    $response->assertUnauthorized();
});
