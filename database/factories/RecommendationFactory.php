<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VitalSignsRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Recommendation>
 */
class RecommendationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $recommendationType = fake()->randomElement(['congratulation', 'suggestion', 'warning', 'alert']);
        $severity = fake()->randomElement(['low', 'medium', 'high', 'critical']);

        $recommendations = [
            'congratulation' => [
                'titles' => ['Great Progress!', 'Excellent Work!', 'Keep It Up!', 'Well Done!'],
                'messages' => [
                    'Your blood pressure readings have been consistently within the normal range.',
                    'Congratulations on maintaining healthy vital signs this week.',
                    'Your commitment to health monitoring is paying off.',
                    'Your weight management efforts are showing positive results.',
                ],
            ],
            'suggestion' => [
                'titles' => ['Health Tip', 'Consider This', 'Suggestion', 'Recommendation'],
                'messages' => [
                    'Consider taking your blood pressure at the same time each day for consistency.',
                    'Try monitoring your weight weekly rather than daily for better trends.',
                    'Consider discussing your recent readings with your healthcare provider.',
                    'Regular exercise can help improve your vital sign patterns.',
                ],
            ],
            'warning' => [
                'titles' => ['Attention Needed', 'Important Notice', 'Health Alert', 'Please Review'],
                'messages' => [
                    'Your blood pressure readings have been trending higher than normal.',
                    'We noticed some concerning patterns in your vital signs.',
                    'Your recent glucose levels require attention.',
                    'Consider scheduling a check-up based on recent measurements.',
                ],
            ],
            'alert' => [
                'titles' => ['Urgent', 'Critical Alert', 'Immediate Attention', 'Emergency'],
                'messages' => [
                    'Your blood pressure reading is critically high. Seek immediate medical attention.',
                    'Extremely low oxygen saturation detected. Contact your doctor immediately.',
                    'Dangerous glucose levels detected. Please contact emergency services.',
                    'Critical vital signs detected. Immediate medical evaluation required.',
                ],
            ],
        ];

        $config = $recommendations[$recommendationType];

        return [
            'id' => Str::uuid(),
            'user_id' => User::factory(),
            'vital_signs_record_id' => fake()->boolean(70) ? VitalSignsRecord::factory() : null,
            'recommendation_type' => $recommendationType,
            'title' => fake()->randomElement($config['titles']),
            'message' => fake()->randomElement($config['messages']),
            'severity' => $severity,
            'action_required' => fake()->boolean($recommendationType === 'alert' ? 95 : ($recommendationType === 'warning' ? 60 : 20)),
            'read_at' => fake()->boolean(60) ? fake()->dateTimeBetween('-7 days', 'now') : null,
            'dismissed_at' => fake()->boolean(30) ? fake()->dateTimeBetween('-3 days', 'now') : null,
            'expires_at' => fake()->boolean(80) ? fake()->dateTimeBetween('now', '+30 days') : null,
            'metadata' => fake()->optional(0.5)->randomElement([
                ['source' => 'automated_analysis', 'confidence' => fake()->randomFloat(2, 0.7, 1.0)],
                ['trigger_value' => fake()->randomFloat(2, 80, 200), 'threshold' => fake()->randomFloat(2, 140, 180)],
                ['trend_direction' => fake()->randomElement(['increasing', 'decreasing', 'stable'])],
                ['related_conditions' => fake()->randomElements(['hypertension', 'diabetes', 'obesity'], fake()->numberBetween(1, 2))],
            ]),
        ];
    }

    /**
     * Create a congratulation recommendation.
     */
    public function congratulation(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommendation_type' => 'congratulation',
            'severity' => 'low',
            'title' => fake()->randomElement(['Great Progress!', 'Excellent Work!', 'Keep It Up!']),
            'message' => 'Your vital signs have been consistently within healthy ranges. Keep up the excellent work!',
            'action_required' => false,
        ]);
    }

    /**
     * Create a suggestion recommendation.
     */
    public function suggestion(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommendation_type' => 'suggestion',
            'severity' => fake()->randomElement(['low', 'medium']),
            'title' => 'Health Suggestion',
            'action_required' => fake()->boolean(30),
        ]);
    }

    /**
     * Create a warning recommendation.
     */
    public function warning(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommendation_type' => 'warning',
            'severity' => fake()->randomElement(['medium', 'high']),
            'title' => 'Health Warning',
            'action_required' => fake()->boolean(70),
        ]);
    }

    /**
     * Create a critical alert recommendation.
     */
    public function alert(): static
    {
        return $this->state(fn (array $attributes) => [
            'recommendation_type' => 'alert',
            'severity' => 'critical',
            'title' => 'Critical Health Alert',
            'action_required' => true,
            'expires_at' => fake()->dateTimeBetween('now', '+7 days'),
        ]);
    }

    /**
     * Create an unread recommendation.
     */
    public function unread(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => null,
            'dismissed_at' => null,
        ]);
    }

    /**
     * Create a read recommendation.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-7 days', 'now'),
        ]);
    }

    /**
     * Create a dismissed recommendation.
     */
    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => fake()->dateTimeBetween('-7 days', '-1 day'),
            'dismissed_at' => fake()->dateTimeBetween('-3 days', 'now'),
        ]);
    }

    /**
     * Create an expired recommendation.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => fake()->dateTimeBetween('-30 days', '-1 day'),
        ]);
    }

    /**
     * Create a recent recommendation (within last 24 hours).
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => fake()->dateTimeBetween('-24 hours', 'now'),
            'read_at' => null,
        ]);
    }
}
