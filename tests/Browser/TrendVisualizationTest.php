<?php

use App\Models\User;
use App\Models\VitalSignType;
use App\Models\VitalSignsRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use function Pest\Laravel\{actingAs};

uses(RefreshDatabase::class);

describe('Trend Visualization Browser Flow', function () {
    beforeEach(function () {
        $this->user = User::factory()->create([
            'name' => 'Sarah Analytics',
            'email' => 'sarah@example.com',
        ]);

        $this->vitalSignTypes = collect([
            VitalSignType::factory()->create([
                'name' => 'blood_pressure',
                'display_name' => 'Blood Pressure',
                'unit_primary' => 'mmHg',
                'unit_secondary' => 'mmHg',
                'has_secondary_value' => true,
                'normal_range_min' => 90,
                'normal_range_max' => 140,
            ]),
            VitalSignType::factory()->create([
                'name' => 'heart_rate',
                'display_name' => 'Heart Rate',
                'unit_primary' => 'bpm',
                'has_secondary_value' => false,
                'normal_range_min' => 60,
                'normal_range_max' => 100,
            ]),
            VitalSignType::factory()->create([
                'name' => 'weight',
                'display_name' => 'Weight',
                'unit_primary' => 'kg',
                'has_secondary_value' => false,
                'normal_range_min' => 50,
                'normal_range_max' => 120,
            ]),
        ]);

        // Create trend data for the last 30 days
        $this->createTrendData();
    });

    private function createTrendData(): void
    {
        $bloodPressureType = $this->vitalSignTypes->where('name', 'blood_pressure')->first();
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();
        $weightType = $this->vitalSignTypes->where('name', 'weight')->first();

        // Blood pressure with increasing trend
        for ($i = 0; $i < 15; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $bloodPressureType->id,
                'value_primary' => (string)(110 + ($i * 2)), // Gradually increasing
                'value_secondary' => (string)(70 + $i),
                'measured_at' => now()->subDays(15 - $i),
            ]);
        }

        // Heart rate with stable pattern
        for ($i = 0; $i < 20; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => (string)(72 + rand(-5, 5)), // Stable with minor variations
                'measured_at' => now()->subDays(20 - $i),
            ]);
        }

        // Weight with decreasing trend
        for ($i = 0; $i < 10; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $weightType->id,
                'value_primary' => (string)(75 - ($i * 0.3)), // Gradually decreasing
                'measured_at' => now()->subDays(10 - $i),
            ]);
        }
    }

    it('displays trends page with chart selection', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->assertSee('Health Trends')
            ->assertSee('Select Vital Sign Type')
            ->assertSee('Blood Pressure')
            ->assertSee('Heart Rate')
            ->assertSee('Weight')
            ->assertSee('Time Period')
            ->assertSee('Last 7 days')
            ->assertSee('Last 30 days')
            ->assertSee('Last 90 days');
    });

    it('displays blood pressure trend chart with increasing pattern', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertSee('Blood Pressure Trend')
            ->assertSee('Increasing Trend Detected')
            ->assertSee('15 readings')
            ->assertSee('Average: ')
            ->assertSee('mmHg')
            ->assertElementExists('[data-testid="chart-legend"]')
            ->assertElementExists('[data-testid="trend-line"]');

        // Verify chart data points are visible
        expect($this->page->evaluate('document.querySelectorAll("[data-testid=chart-point]").length'))
            ->toBeGreaterThan(10);
    });

    it('displays heart rate trend chart with stable pattern', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertSee('Heart Rate Trend')
            ->assertSee('Stable Pattern')
            ->assertSee('20 readings')
            ->assertSee('bpm')
            ->assertElementExists('[data-testid="normal-range-indicator"]');
    });

    it('displays weight trend chart with decreasing pattern', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'weight')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertSee('Weight Trend')
            ->assertSee('Decreasing Trend Detected')
            ->assertSee('10 readings')
            ->assertSee('kg')
            ->assertElementExists('[data-testid="trend-arrow-down"]');
    });

    it('allows period filtering and updates chart accordingly', function () {
        $page = visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id);

        // Test 7-day period
        $page->select('period', '7')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertSee('Last 7 days');

        // Test 90-day period
        $page->select('period', '90')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertSee('Last 90 days');
    });

    it('shows statistical analysis for trends', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="statistics-panel"]')
            ->assertSee('Statistics')
            ->assertSee('Average:')
            ->assertSee('Highest:')
            ->assertSee('Lowest:')
            ->assertSee('Trend Direction:')
            ->assertSee('Increasing')
            ->assertSee('Normal Range:')
            ->assertSee('90-140 mmHg');
    });

    it('highlights abnormal readings on the chart', function () {
        // Create an abnormal reading
        VitalSignsRecord::factory()->create([
            'user_id' => $this->user->id,
            'vital_sign_type_id' => $this->vitalSignTypes->where('name', 'heart_rate')->first()->id,
            'value_primary' => '130', // Above normal range
            'is_flagged' => true,
            'measured_at' => now()->subDays(5),
        ]);

        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertElementExists('[data-testid="flagged-reading"]')
            ->hover('[data-testid="flagged-reading"]')
            ->assertSee('Abnormal Reading')
            ->assertSee('130 bpm');
    });

    it('supports chart interaction and tooltips', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->hover('[data-testid="chart-point"]:first-of-type')
            ->assertSee('Date:')
            ->assertSee('Value:')
            ->assertSee('mmHg')
            ->click('[data-testid="chart-point"]:first-of-type')
            ->assertSee('Reading Details'); // Should show detailed view
    });

    it('exports chart data and generates reports', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->click('Export Data')
            ->assertSee('Export Options')
            ->click('Download CSV')
            ->waitFor('[data-testid="download-success"]')
            ->assertSee('Data exported successfully');

        // Test PDF report generation
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->click('Generate Report')
            ->assertSee('Report Options')
            ->check('include_statistics')
            ->check('include_chart')
            ->click('Generate PDF')
            ->waitFor('[data-testid="report-ready"]')
            ->assertSee('Report generated successfully');
    });

    it('displays empty state when no data available', function () {
        // Create a new user with no vital signs data
        $newUser = User::factory()->create();

        visit('/vital-signs/trends')
            ->actingAs($newUser)
            ->assertNoJavascriptErrors()
            ->assertSee('No Data Available')
            ->assertSee('Start by recording some vital signs')
            ->assertSee('Record Your First Reading')
            ->click('Record Your First Reading')
            ->assertUrlContains('/vital-signs/create');
    });

    it('handles large datasets with pagination and performance', function () {
        // Create a large dataset
        $heartRateType = $this->vitalSignTypes->where('name', 'heart_rate')->first();

        for ($i = 0; $i < 100; $i++) {
            VitalSignsRecord::factory()->create([
                'user_id' => $this->user->id,
                'vital_sign_type_id' => $heartRateType->id,
                'value_primary' => (string)(70 + rand(-10, 10)),
                'measured_at' => now()->subDays(rand(1, 365)),
            ]);
        }

        $startTime = microtime(true);

        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $heartRateType->id)
            ->select('period', '365') // Full year
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]', 10)
            ->assertSee('120 readings') // Original 20 + new 100
            ->assertElementExists('[data-testid="chart-pagination"]');

        $loadTime = microtime(true) - $startTime;
        expect($loadTime)->toBeLessThan(5); // Should load within 5 seconds
    });

    it('supports responsive chart display on different screen sizes', function () {
        // Test mobile view
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->resize(375, 812) // iPhone X
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'heart_rate')->first()->id)
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertElementExists('[data-testid="mobile-chart"]')
            ->assertSee('Swipe to navigate');

        // Test tablet view
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->resize(768, 1024) // iPad
            ->assertNoJavascriptErrors()
            ->waitFor('[data-testid="trend-chart"]')
            ->assertElementExists('[data-testid="tablet-chart"]');

        // Test desktop view
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->resize(1200, 800) // Desktop
            ->assertNoJavascriptErrors()
            ->waitFor('[data-testid="trend-chart"]')
            ->assertElementExists('[data-testid="desktop-chart"]')
            ->assertSee('Zoom Controls');
    });

    it('supports dark mode chart rendering', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->click('[data-testid="theme-toggle"]') // Switch to dark mode
            ->waitFor('[data-theme="dark"]')
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->assertElementHasClass('[data-testid="trend-chart"]', 'dark-theme')
            ->assertCssProperty('[data-testid="chart-background"]', 'background-color', 'rgb(17, 24, 39)'); // dark gray
    });

    it('compares multiple vital signs on the same chart', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->check('compare_mode')
            ->assertSee('Compare Multiple Vital Signs')
            ->check('blood_pressure')
            ->check('heart_rate')
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="comparison-chart"]')
            ->assertSee('Blood Pressure vs Heart Rate')
            ->assertElementExists('[data-testid="legend-blood-pressure"]')
            ->assertElementExists('[data-testid="legend-heart-rate"]')
            ->assertElementExists('[data-testid="dual-y-axis"]');
    });

    it('provides trend insights and recommendations', function () {
        visit('/vital-signs/trends')
            ->actingAs($this->user)
            ->assertNoJavascriptErrors()
            ->select('vital_sign_type', $this->vitalSignTypes->where('name', 'blood_pressure')->first()->id)
            ->select('period', '30')
            ->click('Update Chart')
            ->waitFor('[data-testid="trend-chart"]')
            ->click('View Insights')
            ->waitFor('[data-testid="insights-panel"]')
            ->assertSee('Trend Analysis')
            ->assertSee('Your blood pressure has been increasing over the last 30 days')
            ->assertSee('Recommendations')
            ->assertSee('Consider consulting with a healthcare provider')
            ->assertSee('Monitor more frequently')
            ->assertElementExists('[data-testid="share-insights"]');
    });
});