<?php

namespace App\Console\Commands;

use App\Jobs\GenerateRecommendations as GenerateRecommendationsJob;
use App\Models\User;
use App\Services\RecommendationGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Queue;

class GenerateRecommendations extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'health:generate-recommendations
                            {--user= : Process recommendations for specific user ID}
                            {--queue= : Queue name to dispatch jobs to (default: default)}
                            {--sync : Run synchronously instead of queuing}
                            {--lookback-days=30 : Number of days to look back for vital signs data}
                            {--min-readings=3 : Minimum number of readings required for analysis}
                            {--types=* : Recommendation types to generate (health_alert,lifestyle,trend_observation,goal_progress)}
                            {--force : Force regeneration of existing recommendations}
                            {--dry-run : Show what would be processed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate personalized health recommendations for users based on their vital signs data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🩺 Health Recommendations Generator');
        $this->newLine();

        // Validate and prepare options
        $options = $this->prepareOptions();
        if (! $options) {
            return self::FAILURE;
        }

        $userId = $this->option('user');
        $sync = $this->option('sync');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('🧪 DRY RUN MODE - No changes will be made');
            $this->newLine();
        }

        // Show configuration summary
        $this->displayConfiguration($options, $userId, $sync);

        // Get user confirmation for batch processing
        if (! $userId && ! $this->option('no-interaction')) {
            if (! $this->confirm('This will process recommendations for ALL users. Continue?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
            $this->newLine();
        }

        // Start processing
        $startTime = microtime(true);

        try {
            if ($sync) {
                // Run synchronously
                $this->info('🔄 Processing recommendations synchronously...');
                $result = $this->processSynchronously($userId, $options);
            } else {
                // Queue the job
                $this->info('📋 Dispatching recommendations job to queue...');
                $result = $this->dispatchToQueue($userId, $options);
            }

            $executionTime = round(microtime(true) - $startTime, 2);

            if ($result['success']) {
                $this->displaySuccess($result, $executionTime, $sync);

                return self::SUCCESS;
            } else {
                $this->displayError($result);

                return self::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("❌ An error occurred: {$e->getMessage()}");
            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    /**
     * Prepare and validate command options.
     */
    private function prepareOptions(): array|false
    {
        $lookbackDays = (int) $this->option('lookback-days');
        $minReadings = (int) $this->option('min-readings');
        $types = $this->option('types');

        // Validate lookback days
        if ($lookbackDays < 1 || $lookbackDays > 365) {
            $this->error('Lookback days must be between 1 and 365');

            return false;
        }

        // Validate minimum readings
        if ($minReadings < 1 || $minReadings > 50) {
            $this->error('Minimum readings must be between 1 and 50');

            return false;
        }

        // Validate user ID if provided
        if ($userId = $this->option('user')) {
            if (! is_numeric($userId) || ! User::find($userId)) {
                $this->error("User with ID {$userId} not found");

                return false;
            }
        }

        // Prepare recommendation types
        $availableTypes = ['health_alert', 'lifestyle', 'trend_observation', 'goal_progress'];
        if (empty($types)) {
            $types = $availableTypes;
        } else {
            $invalidTypes = array_diff($types, $availableTypes);
            if (! empty($invalidTypes)) {
                $this->error('Invalid recommendation types: '.implode(', ', $invalidTypes));
                $this->info('Available types: '.implode(', ', $availableTypes));

                return false;
            }
        }

        return [
            'lookback_days' => $lookbackDays,
            'min_readings_required' => $minReadings,
            'recommendation_types' => $types,
            'force_regenerate' => $this->option('force'),
            'dry_run' => $this->option('dry-run'),
        ];
    }

    /**
     * Display configuration summary.
     */
    private function displayConfiguration(array $options, ?string $userId, bool $sync): void
    {
        $this->info('📋 Configuration:');

        if ($userId) {
            $user = User::find($userId);
            $this->line("   Target: User #{$userId} ({$user->name})");
        } else {
            $this->line('   Target: All users with recent vital signs');
        }

        $this->line('   Lookback: '.$options['lookback_days'].' days');
        $this->line('   Min readings: '.$options['min_readings_required']);
        $this->line('   Types: '.implode(', ', $options['recommendation_types']));
        $this->line('   Mode: '.($sync ? 'Synchronous' : 'Queued'));
        $this->line('   Force regenerate: '.($options['force_regenerate'] ? 'Yes' : 'No'));

        if ($this->option('queue')) {
            $this->line('   Queue: '.$this->option('queue'));
        }

        $this->newLine();
    }

    /**
     * Process recommendations synchronously.
     */
    private function processSynchronously(?string $userId, array $options): array
    {
        $job = new GenerateRecommendationsJob(
            $userId ? (int) $userId : null,
            $userId === null, // processAllUsers
            $options
        );

        // Execute job directly
        ob_start();
        $job->handle(app(RecommendationGenerationService::class));
        $output = ob_get_clean();

        if ($this->option('verbose') && $output) {
            $this->info('Job output:');
            $this->line($output);
        }

        return [
            'success' => true,
            'method' => 'synchronous',
            'message' => 'Recommendations processed successfully',
        ];
    }

    /**
     * Dispatch job to queue.
     */
    private function dispatchToQueue(?string $userId, array $options): array
    {
        $queueName = $this->option('queue') ?: 'default';

        $job = new GenerateRecommendationsJob(
            $userId ? (int) $userId : null,
            $userId === null, // processAllUsers
            $options
        );

        // Dispatch to specific queue
        $job->onQueue($queueName);
        Queue::push($job);

        return [
            'success' => true,
            'method' => 'queued',
            'queue' => $queueName,
            'message' => 'Job dispatched to queue successfully',
        ];
    }

    /**
     * Display success message.
     */
    private function displaySuccess(array $result, float $executionTime, bool $sync): void
    {
        $this->newLine();
        $this->info('✅ '.$result['message']);

        if ($sync) {
            $this->info("⏱️  Execution time: {$executionTime}s");
        } else {
            $this->info("📋 Queue: {$result['queue']}");
            $this->info("⏱️  Command time: {$executionTime}s");
            $this->line('   (Job will process asynchronously)');
        }

        $this->newLine();
        $this->info('💡 Tips:');
        $this->line('   • Check logs for detailed processing information');
        $this->line('   • Use --dry-run to preview changes without saving');
        $this->line('   • Use --verbose for more detailed output');

        if (! $sync) {
            $this->line('   • Monitor queue with: php artisan queue:work');
        }
    }

    /**
     * Display error message.
     */
    private function displayError(array $result): void
    {
        $this->newLine();
        $this->error('❌ '.($result['message'] ?? 'Processing failed'));

        if (isset($result['details'])) {
            $this->error('Details: '.$result['details']);
        }
    }

    /**
     * Get command usage examples.
     */
    public function getExamples(): array
    {
        return [
            'Generate recommendations for all users' => 'php artisan health:generate-recommendations',
            'Generate for specific user' => 'php artisan health:generate-recommendations --user=123',
            'Run synchronously with verbose output' => 'php artisan health:generate-recommendations --sync --verbose',
            'Dry run to preview changes' => 'php artisan health:generate-recommendations --dry-run',
            'Generate only health alerts' => 'php artisan health:generate-recommendations --types=health_alert',
            'Force regeneration of existing recommendations' => 'php artisan health:generate-recommendations --force',
            'Custom lookback period' => 'php artisan health:generate-recommendations --lookback-days=14',
            'Dispatch to specific queue' => 'php artisan health:generate-recommendations --queue=health-processing',
        ];
    }
}
