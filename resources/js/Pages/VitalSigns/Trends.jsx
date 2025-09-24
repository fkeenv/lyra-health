import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import { TrendingUp, TrendingDown, Activity, Calendar, BarChart3 } from 'lucide-react';

// Simple Line Chart Component
function SimpleLineChart({ data, selectedType, height = 240 }) {
  if (!data || data.length === 0) {
    return (
      <div className="flex items-center justify-center h-full text-muted-foreground">
        No data to display
      </div>
    );
  }

  const width = 800;
  const padding = 40;
  const chartWidth = width - 2 * padding;
  const chartHeight = height - 2 * padding;

  // Prepare data points
  const values = data.map(d => parseFloat(d.value_primary));
  const dates = data.map(d => new Date(d.measured_at));

  // Calculate scales
  const minValue = Math.min(...values);
  const maxValue = Math.max(...values);
  const valueRange = maxValue - minValue || 1;

  const minDate = Math.min(...dates.map(d => d.getTime()));
  const maxDate = Math.max(...dates.map(d => d.getTime()));
  const dateRange = maxDate - minDate || 1;

  // Create path points
  const points = data.map((d, i) => {
    const x = padding + (chartWidth * (dates[i].getTime() - minDate)) / dateRange;
    const y = padding + chartHeight - ((parseFloat(d.value_primary) - minValue) / valueRange) * chartHeight;
    return { x, y, value: d.value_primary, date: dates[i], flagged: d.is_flagged };
  });

  // Create path string
  const pathData = points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.y}`).join(' ');

  // Normal range lines (if available)
  const normalMin = selectedType?.normal_range_min;
  const normalMax = selectedType?.normal_range_max;
  const normalMinY = normalMin ? padding + chartHeight - ((normalMin - minValue) / valueRange) * chartHeight : null;
  const normalMaxY = normalMax ? padding + chartHeight - ((normalMax - minValue) / valueRange) * chartHeight : null;

  return (
    <div className="w-full h-full">
      <svg width="100%" height={height} viewBox={`0 0 ${width} ${height}`} className="overflow-visible">
        {/* Background grid */}
        <defs>
          <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="hsl(var(--muted))" strokeWidth="0.5" opacity="0.5"/>
          </pattern>
        </defs>
        <rect width={chartWidth} height={chartHeight} x={padding} y={padding} fill="url(#grid)" />

        {/* Normal range bands */}
        {normalMinY !== null && normalMaxY !== null && (
          <rect
            x={padding}
            y={normalMaxY}
            width={chartWidth}
            height={normalMinY - normalMaxY}
            fill="hsl(var(--primary))"
            opacity="0.1"
          />
        )}

        {/* Normal range lines */}
        {normalMinY !== null && (
          <line
            x1={padding}
            y1={normalMinY}
            x2={padding + chartWidth}
            y2={normalMinY}
            stroke="hsl(var(--primary))"
            strokeWidth="1"
            strokeDasharray="4,4"
            opacity="0.6"
          />
        )}
        {normalMaxY !== null && (
          <line
            x1={padding}
            y1={normalMaxY}
            x2={padding + chartWidth}
            y2={normalMaxY}
            stroke="hsl(var(--primary))"
            strokeWidth="1"
            strokeDasharray="4,4"
            opacity="0.6"
          />
        )}

        {/* Data line */}
        <path
          d={pathData}
          fill="none"
          stroke="hsl(var(--primary))"
          strokeWidth="2"
          strokeLinecap="round"
          strokeLinejoin="round"
        />

        {/* Data points */}
        {points.map((point, i) => (
          <g key={i}>
            <circle
              cx={point.x}
              cy={point.y}
              r={point.flagged ? "6" : "4"}
              fill={point.flagged ? "hsl(var(--destructive))" : "hsl(var(--primary))"}
              stroke="white"
              strokeWidth="2"
            />
            {/* Tooltip on hover */}
            <title>
              {point.date.toLocaleDateString()}: {point.value} {data[0].unit}
              {point.flagged ? ' (Flagged)' : ''}
            </title>
          </g>
        ))}

        {/* Y-axis labels */}
        <text x={padding - 10} y={padding} fill="hsl(var(--muted-foreground))" fontSize="12" textAnchor="end" dominantBaseline="central">
          {maxValue.toFixed(1)}
        </text>
        <text x={padding - 10} y={padding + chartHeight} fill="hsl(var(--muted-foreground))" fontSize="12" textAnchor="end" dominantBaseline="central">
          {minValue.toFixed(1)}
        </text>

        {/* X-axis labels */}
        <text x={padding} y={height - 10} fill="hsl(var(--muted-foreground))" fontSize="12" textAnchor="start">
          {dates[0].toLocaleDateString()}
        </text>
        <text x={padding + chartWidth} y={height - 10} fill="hsl(var(--muted-foreground))" fontSize="12" textAnchor="end">
          {dates[dates.length - 1].toLocaleDateString()}
        </text>
      </svg>
    </div>
  );
}

export default function VitalSignsTrends({ vitalSignTypes = [] }) {
  const [selectedType, setSelectedType] = useState('');
  const [selectedPeriod, setSelectedPeriod] = useState('30');
  const [trendsData, setTrendsData] = useState([]);
  const [loading, setLoading] = useState(false);
  const [statistics, setStatistics] = useState(null);

  // Load data when type or period changes
  useEffect(() => {
    if (selectedType) {
      loadTrendsData();
    }
  }, [selectedType, selectedPeriod]);

  const loadTrendsData = async () => {
    if (!selectedType) return;

    setLoading(true);
    try {
      // Use the new trends API endpoint
      const endDate = new Date().toISOString().split('T')[0];
      const startDate = new Date(Date.now() - parseInt(selectedPeriod) * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

      const response = await fetch(`/api/trends/${selectedType}?start_date=${startDate}&end_date=${endDate}&period=daily&group_by=day&include_averages=true`, {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to load trends data');
      }

      const data = await response.json();

      // Transform the trends API response to match our component expectations
      if (data.data && data.data.trends && data.data.trends.data_points) {
        const transformedData = data.data.trends.data_points.map(point => ({
          measured_at: point.date,
          value_primary: point.value,
          value_secondary: point.secondary_value || null,
          unit: data.data.vital_sign_type.unit_primary,
          is_flagged: point.is_flagged || false,
        }));
        setTrendsData(transformedData);
        calculateStatistics(transformedData);
      } else {
        // Fallback to fetching raw vital signs data
        const fallbackResponse = await fetch(`/api/vital-signs?vital_sign_type_id=${selectedType}&start_date=${startDate}&end_date=${endDate}&per_page=100`, {
          headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
          },
        });

        if (fallbackResponse.ok) {
          const fallbackData = await fallbackResponse.json();
          setTrendsData(fallbackData.data || []);
          calculateStatistics(fallbackData.data || []);
        } else {
          setTrendsData([]);
          setStatistics(null);
        }
      }
    } catch (error) {
      console.error('Error loading trends data:', error);
      setTrendsData([]);
      setStatistics(null);
    } finally {
      setLoading(false);
    }
  };

  const calculateStatistics = (data) => {
    if (!data.length) {
      setStatistics(null);
      return;
    }

    const values = data.map(item => parseFloat(item.value_primary));
    const latest = values[values.length - 1];
    const previous = values.length > 1 ? values[values.length - 2] : latest;
    const average = values.reduce((sum, val) => sum + val, 0) / values.length;
    const min = Math.min(...values);
    const max = Math.max(...values);

    // Calculate trend
    const trend = latest > previous ? 'up' : latest < previous ? 'down' : 'stable';
    const changePercent = previous !== 0 ? ((latest - previous) / previous) * 100 : 0;

    setStatistics({
      latest,
      average: parseFloat(average.toFixed(2)),
      min,
      max,
      trend,
      changePercent: parseFloat(changePercent.toFixed(1)),
      count: data.length,
    });
  };

  const formatValue = (record) => {
    let value = record.value_primary;
    if (record.value_secondary) {
      value += `/${record.value_secondary}`;
    }
    return `${value} ${record.unit}`;
  };

  const getSelectedTypeName = () => {
    const type = vitalSignTypes.find(t => t.id.toString() === selectedType);
    return type ? type.display_name : '';
  };

  const getStatusColor = (value, type) => {
    if (!type) return 'text-foreground';

    const numValue = parseFloat(value);
    if (numValue < type.normal_range_min || numValue > type.normal_range_max) {
      return 'text-red-600';
    }
    if (numValue < type.warning_range_min || numValue > type.warning_range_max) {
      return 'text-orange-600';
    }
    return 'text-green-600';
  };

  const selectedVitalType = vitalSignTypes.find(t => t.id.toString() === selectedType);

  return (
    <AppLayout title="Health Trends">
      <Head title="Health Trends" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Health Trends</h1>
            <p className="text-muted-foreground">
              Track your vital signs over time and identify patterns
            </p>
          </div>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Filter & View Options
            </CardTitle>
            <CardDescription>
              Select the vital sign type and time period to analyze
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-medium">Vital Sign Type</label>
                <Select value={selectedType} onValueChange={setSelectedType}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select vital sign to analyze" />
                  </SelectTrigger>
                  <SelectContent>
                    {vitalSignTypes.map((type) => (
                      <SelectItem key={type.id} value={type.id.toString()}>
                        {type.display_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Time Period</label>
                <Select value={selectedPeriod} onValueChange={setSelectedPeriod}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="7">Last 7 days</SelectItem>
                    <SelectItem value="30">Last 30 days</SelectItem>
                    <SelectItem value="90">Last 3 months</SelectItem>
                    <SelectItem value="180">Last 6 months</SelectItem>
                    <SelectItem value="365">Last year</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {selectedType && (
          <>
            {/* Statistics */}
            {statistics && (
              <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                <Card>
                  <CardContent className="p-6">
                    <div className="flex items-center space-x-2">
                      <Activity className="h-4 w-4 text-muted-foreground" />
                      <span className="text-sm font-medium text-muted-foreground">Latest</span>
                    </div>
                    <div className="mt-2">
                      <div className="text-2xl font-bold">{statistics.latest}</div>
                      <div className="flex items-center space-x-1 text-xs text-muted-foreground">
                        {statistics.trend === 'up' && (
                          <>
                            <TrendingUp className="h-3 w-3 text-green-600" />
                            <span className="text-green-600">+{statistics.changePercent}%</span>
                          </>
                        )}
                        {statistics.trend === 'down' && (
                          <>
                            <TrendingDown className="h-3 w-3 text-red-600" />
                            <span className="text-red-600">{statistics.changePercent}%</span>
                          </>
                        )}
                        {statistics.trend === 'stable' && (
                          <span className="text-muted-foreground">No change</span>
                        )}
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardContent className="p-6">
                    <div className="flex items-center space-x-2">
                      <BarChart3 className="h-4 w-4 text-muted-foreground" />
                      <span className="text-sm font-medium text-muted-foreground">Average</span>
                    </div>
                    <div className="mt-2">
                      <div className="text-2xl font-bold">{statistics.average}</div>
                      <div className="text-xs text-muted-foreground">
                        {selectedVitalType?.unit_primary}
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardContent className="p-6">
                    <div className="flex items-center space-x-2">
                      <TrendingUp className="h-4 w-4 text-muted-foreground" />
                      <span className="text-sm font-medium text-muted-foreground">Range</span>
                    </div>
                    <div className="mt-2">
                      <div className="text-2xl font-bold">{statistics.min} - {statistics.max}</div>
                      <div className="text-xs text-muted-foreground">
                        Min to Max
                      </div>
                    </div>
                  </CardContent>
                </Card>

                <Card>
                  <CardContent className="p-6">
                    <div className="flex items-center space-x-2">
                      <Calendar className="h-4 w-4 text-muted-foreground" />
                      <span className="text-sm font-medium text-muted-foreground">Records</span>
                    </div>
                    <div className="mt-2">
                      <div className="text-2xl font-bold">{statistics.count}</div>
                      <div className="text-xs text-muted-foreground">
                        Total measurements
                      </div>
                    </div>
                  </CardContent>
                </Card>
              </div>
            )}

            {/* Chart Placeholder & Data */}
            <Card>
              <CardHeader>
                <CardTitle>{getSelectedTypeName()} Trends</CardTitle>
                <CardDescription>
                  Your measurements over the selected time period
                </CardDescription>
              </CardHeader>
              <CardContent>
                {loading ? (
                  <div className="flex items-center justify-center h-64">
                    <div className="text-muted-foreground">Loading trends data...</div>
                  </div>
                ) : trendsData.length > 0 ? (
                  <div className="space-y-4">
                    {/* Simple Line Chart */}
                    <div className="h-64 border rounded-lg p-4 bg-card">
                      <SimpleLineChart
                        data={trendsData}
                        selectedType={selectedVitalType}
                        height={240}
                      />
                    </div>

                    {/* Data Table */}
                    <div className="border rounded-lg">
                      <div className="p-4 border-b bg-muted/50">
                        <h3 className="font-medium">Recent Measurements</h3>
                      </div>
                      <div className="max-h-96 overflow-y-auto">
                        {trendsData.slice(0, 20).map((record, index) => (
                          <div key={index} className="flex items-center justify-between p-4 border-b last:border-b-0">
                            <div>
                              <div className="font-medium">
                                {new Date(record.measured_at).toLocaleDateString()}
                              </div>
                              <div className="text-sm text-muted-foreground">
                                {new Date(record.measured_at).toLocaleTimeString()}
                              </div>
                            </div>
                            <div className="text-right">
                              <div className={`font-semibold ${getStatusColor(record.value_primary, selectedVitalType)}`}>
                                {formatValue(record)}
                              </div>
                              {record.is_flagged && (
                                <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                  Flagged
                                </span>
                              )}
                            </div>
                          </div>
                        ))}
                      </div>
                    </div>
                  </div>
                ) : (
                  <div className="text-center py-12">
                    <Activity className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                    <div className="text-lg font-medium mb-2">No data available</div>
                    <p className="text-muted-foreground mb-4">
                      No measurements found for {getSelectedTypeName()} in the selected time period.
                    </p>
                    <Button onClick={() => window.location.href = '/vital-signs/create'}>
                      Record Your First Measurement
                    </Button>
                  </div>
                )}
              </CardContent>
            </Card>
          </>
        )}

        {!selectedType && (
          <Card>
            <CardContent className="text-center py-12">
              <BarChart3 className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
              <div className="text-lg font-medium mb-2">Select a Vital Sign Type</div>
              <p className="text-muted-foreground">
                Choose a vital sign type from the filter above to view your health trends and patterns.
              </p>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}