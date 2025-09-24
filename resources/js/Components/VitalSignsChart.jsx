import React, { useState, useEffect, useMemo } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Calendar, TrendingUp, TrendingDown, Minus, AlertTriangle, Info } from 'lucide-react';

const VitalSignsChart = ({
  data = [],
  vitalSignTypes = [],
  selectedTypeId = null,
  onTypeChange = null,
  height = 300,
  width = '100%',
  showControls = true,
  showStats = true,
  showNormalRange = true,
  period = 7,
  onPeriodChange = null,
  className = ""
}) => {
  const [hoveredPoint, setHoveredPoint] = useState(null);
  const [showFlaggedOnly, setShowFlaggedOnly] = useState(false);

  // Filter data based on selected type and flagged filter
  const filteredData = useMemo(() => {
    let filtered = data;

    if (selectedTypeId) {
      filtered = filtered.filter(d => d.vital_sign_type_id === parseInt(selectedTypeId));
    }

    if (showFlaggedOnly) {
      filtered = filtered.filter(d => d.is_flagged);
    }

    // Sort by measurement date
    return filtered.sort((a, b) => new Date(a.measured_at) - new Date(b.measured_at));
  }, [data, selectedTypeId, showFlaggedOnly]);

  // Get selected vital sign type
  const selectedType = useMemo(() => {
    return vitalSignTypes.find(type => type.id === parseInt(selectedTypeId));
  }, [vitalSignTypes, selectedTypeId]);

  // Calculate statistics
  const stats = useMemo(() => {
    if (filteredData.length === 0) {
      return { count: 0, avg: 0, min: 0, max: 0, trend: 'stable', flagged: 0 };
    }

    const values = filteredData.map(d => parseFloat(d.value_primary));
    const flagged = filteredData.filter(d => d.is_flagged).length;

    // Calculate trend using simple linear regression
    const n = values.length;
    let trend = 'stable';

    if (n > 1) {
      const xValues = Array.from({length: n}, (_, i) => i + 1);
      const xAvg = xValues.reduce((a, b) => a + b, 0) / n;
      const yAvg = values.reduce((a, b) => a + b, 0) / n;

      const numerator = xValues.reduce((sum, x, i) => sum + (x - xAvg) * (values[i] - yAvg), 0);
      const denominator = xValues.reduce((sum, x) => sum + Math.pow(x - xAvg, 2), 0);

      if (denominator !== 0) {
        const slope = numerator / denominator;
        if (Math.abs(slope) > 0.1) {
          trend = slope > 0 ? 'increasing' : 'decreasing';
        }
      }
    }

    return {
      count: n,
      avg: +(values.reduce((a, b) => a + b, 0) / n).toFixed(1),
      min: Math.min(...values),
      max: Math.max(...values),
      trend,
      flagged
    };
  }, [filteredData]);

  // Chart calculations
  const chartData = useMemo(() => {
    if (filteredData.length === 0) return null;

    const padding = 40;
    const chartWidth = (typeof width === 'number' ? width : 600) - (2 * padding);
    const chartHeight = height - (2 * padding);

    const values = filteredData.map(d => parseFloat(d.value_primary));
    const dates = filteredData.map(d => new Date(d.measured_at));

    const minValue = Math.min(...values);
    const maxValue = Math.max(...values);
    const valueRange = maxValue - minValue || 1;

    // Add padding to value range
    const valuePadding = valueRange * 0.1;
    const adjustedMin = minValue - valuePadding;
    const adjustedMax = maxValue + valuePadding;
    const adjustedRange = adjustedMax - adjustedMin;

    const minDate = Math.min(...dates);
    const maxDate = Math.max(...dates);
    const dateRange = maxDate - minDate || 86400000; // 1 day fallback

    // Generate points
    const points = filteredData.map((d, i) => {
      const x = padding + (chartWidth * (dates[i].getTime() - minDate)) / dateRange;
      const y = padding + chartHeight - ((parseFloat(d.value_primary) - adjustedMin) / adjustedRange) * chartHeight;

      return {
        x,
        y,
        data: d,
        value: parseFloat(d.value_primary),
        date: dates[i]
      };
    });

    // Generate path
    const pathData = points.length > 0 ?
      'M ' + points.map(p => `${p.x},${p.y}`).join(' L ') : '';

    // Generate normal range path if available
    let normalRangePath = null;
    if (showNormalRange && selectedType && selectedType.normal_range_min && selectedType.normal_range_max) {
      const normalMin = selectedType.normal_range_min;
      const normalMax = selectedType.normal_range_max;

      if (normalMin >= adjustedMin && normalMax <= adjustedMax) {
        const yMin = padding + chartHeight - ((normalMin - adjustedMin) / adjustedRange) * chartHeight;
        const yMax = padding + chartHeight - ((normalMax - adjustedMin) / adjustedRange) * chartHeight;

        normalRangePath = `M ${padding},${yMax} L ${padding + chartWidth},${yMax} L ${padding + chartWidth},${yMin} L ${padding},${yMin} Z`;
      }
    }

    // Generate axis labels
    const yLabels = Array.from({length: 5}, (_, i) => {
      const value = adjustedMin + (adjustedRange * i / 4);
      const y = padding + chartHeight - ((value - adjustedMin) / adjustedRange) * chartHeight;
      return { y, value: +value.toFixed(1) };
    });

    const xLabels = Array.from({length: 5}, (_, i) => {
      const timestamp = minDate + (dateRange * i / 4);
      const x = padding + (chartWidth * i / 4);
      const date = new Date(timestamp);
      return { x, date: date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) };
    });

    return {
      points,
      pathData,
      normalRangePath,
      yLabels,
      xLabels,
      chartArea: { x: padding, y: padding, width: chartWidth, height: chartHeight },
      svgWidth: typeof width === 'number' ? width : 600,
      svgHeight: height
    };
  }, [filteredData, selectedType, showNormalRange, height, width]);

  const formatValue = (record) => {
    if (record.value_secondary) {
      return `${record.value_primary}/${record.value_secondary}`;
    }
    return record.value_primary;
  };

  const formatDate = (date) => {
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const getTrendIcon = (trend) => {
    switch (trend) {
      case 'increasing': return <TrendingUp className="h-4 w-4 text-orange-500" />;
      case 'decreasing': return <TrendingDown className="h-4 w-4 text-blue-500" />;
      default: return <Minus className="h-4 w-4 text-gray-500" />;
    }
  };

  const getTrendColor = (trend) => {
    switch (trend) {
      case 'increasing': return 'text-orange-600';
      case 'decreasing': return 'text-blue-600';
      default: return 'text-gray-600';
    }
  };

  return (
    <Card className={className}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <Calendar className="h-5 w-5" />
              Vital Signs Chart
            </CardTitle>
            <CardDescription>
              {selectedType
                ? `${selectedType.display_name} trends over ${period} days`
                : 'Select a vital sign type to view chart'
              }
            </CardDescription>
          </div>

          {showControls && (
            <div className="flex items-center gap-3">
              {/* Period Controls */}
              {onPeriodChange && (
                <div className="flex rounded-md shadow-sm">
                  {[7, 30, 90].map((days) => (
                    <Button
                      key={days}
                      variant={period === days ? "default" : "outline"}
                      size="sm"
                      className={`${days === 7 ? 'rounded-r-none' : days === 90 ? 'rounded-l-none' : 'rounded-none border-x-0'}`}
                      onClick={() => onPeriodChange(days)}
                    >
                      {days}D
                    </Button>
                  ))}
                </div>
              )}

              {/* Toggles */}
              {selectedType && (
                <div className="flex gap-2">
                  <Button
                    variant={showFlaggedOnly ? "default" : "outline"}
                    size="sm"
                    onClick={() => setShowFlaggedOnly(!showFlaggedOnly)}
                  >
                    Flagged Only
                  </Button>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Type Selector */}
        {!selectedTypeId && onTypeChange && (
          <div className="pt-4">
            <Select onValueChange={onTypeChange}>
              <SelectTrigger className="w-full max-w-md">
                <SelectValue placeholder="Select a vital sign type..." />
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
        )}
      </CardHeader>

      <CardContent>
        {/* Statistics */}
        {showStats && filteredData.length > 0 && (
          <div className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
            <div className="text-center">
              <div className="text-2xl font-bold text-gray-900">{stats.count}</div>
              <div className="text-xs text-gray-500">Readings</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-blue-600">{stats.avg}</div>
              <div className="text-xs text-gray-500">Average</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-green-600">{stats.min}</div>
              <div className="text-xs text-gray-500">Minimum</div>
            </div>
            <div className="text-center">
              <div className="text-2xl font-bold text-red-600">{stats.max}</div>
              <div className="text-xs text-gray-500">Maximum</div>
            </div>
            <div className="text-center">
              <div className={`text-2xl font-bold ${getTrendColor(stats.trend)} flex items-center justify-center`}>
                {getTrendIcon(stats.trend)}
              </div>
              <div className="text-xs text-gray-500 capitalize">{stats.trend}</div>
            </div>
          </div>
        )}

        {/* Chart */}
        {chartData && filteredData.length > 0 ? (
          <div className="relative bg-gray-50 rounded-lg p-4 overflow-x-auto">
            <svg width={chartData.svgWidth} height={chartData.svgHeight} className="w-full">
              {/* Grid */}
              <defs>
                <pattern id="grid" width="50" height="50" patternUnits="userSpaceOnUse">
                  <path d="M 50 0 L 0 0 0 50" fill="none" stroke="#e5e7eb" strokeWidth="1"/>
                </pattern>
              </defs>
              <rect
                x={chartData.chartArea.x}
                y={chartData.chartArea.y}
                width={chartData.chartArea.width}
                height={chartData.chartArea.height}
                fill="url(#grid)"
                opacity="0.5"
              />

              {/* Normal Range Band */}
              {chartData.normalRangePath && (
                <path d={chartData.normalRangePath} fill="#dcfce7" opacity="0.6" />
              )}

              {/* Trend Line */}
              <path d={chartData.pathData} fill="none" stroke="#3b82f6" strokeWidth="3" />

              {/* Data Points */}
              {chartData.points.map((point, index) => (
                <circle
                  key={index}
                  cx={point.x}
                  cy={point.y}
                  r={point.data.is_flagged ? 6 : 4}
                  fill={point.data.is_flagged ? '#ef4444' : '#3b82f6'}
                  stroke="white"
                  strokeWidth="2"
                  className="cursor-pointer hover:r-8 transition-all"
                  onMouseEnter={() => setHoveredPoint(point)}
                  onMouseLeave={() => setHoveredPoint(null)}
                />
              ))}

              {/* Y-axis labels */}
              {chartData.yLabels.map((label, i) => (
                <g key={i}>
                  <text x="35" y={label.y + 4} textAnchor="end" fontSize="12" fill="#6b7280">
                    {label.value}
                  </text>
                  <line
                    x1="38" y1={label.y}
                    x2={chartData.chartArea.x} y2={label.y}
                    stroke="#d1d5db" strokeWidth="1"
                  />
                </g>
              ))}

              {/* X-axis labels */}
              {chartData.xLabels.map((label, i) => (
                <g key={i}>
                  <text x={label.x} y={chartData.svgHeight - 10} textAnchor="middle" fontSize="12" fill="#6b7280">
                    {label.date}
                  </text>
                  <line
                    x1={label.x} y1={chartData.chartArea.y + chartData.chartArea.height}
                    x2={label.x} y2={chartData.chartArea.y + chartData.chartArea.height + 5}
                    stroke="#d1d5db" strokeWidth="1"
                  />
                </g>
              ))}

              {/* Axis lines */}
              <line
                x1={chartData.chartArea.x} y1={chartData.chartArea.y}
                x2={chartData.chartArea.x} y2={chartData.chartArea.y + chartData.chartArea.height}
                stroke="#374151" strokeWidth="2"
              />
              <line
                x1={chartData.chartArea.x} y1={chartData.chartArea.y + chartData.chartArea.height}
                x2={chartData.chartArea.x + chartData.chartArea.width} y2={chartData.chartArea.y + chartData.chartArea.height}
                stroke="#374151" strokeWidth="2"
              />
            </svg>

            {/* Tooltip */}
            {hoveredPoint && (
              <div
                className="absolute z-10 bg-white border border-gray-200 rounded-lg shadow-lg p-3 pointer-events-none"
                style={{
                  left: hoveredPoint.x + 10,
                  top: hoveredPoint.y - 60
                }}
              >
                <div className="text-sm font-medium">
                  {formatValue(hoveredPoint.data)} {selectedType?.unit_primary}
                </div>
                <div className="text-xs text-gray-500">
                  {formatDate(hoveredPoint.date)}
                </div>
                {hoveredPoint.data.is_flagged && (
                  <div className="flex items-center text-xs text-red-600 mt-1">
                    <AlertTriangle className="h-3 w-3 mr-1" />
                    Flagged
                  </div>
                )}
                {hoveredPoint.data.notes && (
                  <div className="text-xs text-gray-600 mt-1 max-w-48">
                    {hoveredPoint.data.notes}
                  </div>
                )}
              </div>
            )}

            {/* Legend */}
            <div className="mt-4 flex flex-wrap items-center justify-center gap-4 text-sm">
              <div className="flex items-center">
                <div className="w-3 h-3 bg-blue-500 rounded-full mr-2"></div>
                <span>Normal Reading</span>
              </div>
              <div className="flex items-center">
                <div className="w-3 h-3 bg-red-500 rounded-full mr-2"></div>
                <span>Flagged Reading</span>
              </div>
              {showNormalRange && selectedType?.normal_range_min && (
                <div className="flex items-center">
                  <div className="w-3 h-3 bg-green-200 mr-2"></div>
                  <span>Normal Range</span>
                </div>
              )}
            </div>
          </div>
        ) : selectedTypeId ? (
          /* No Data State */
          <div className="text-center py-12 bg-gray-50 rounded-lg">
            <Info className="mx-auto h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-sm font-medium text-gray-900 mb-2">No data available</h3>
            <p className="text-sm text-gray-500 mb-2">
              No {selectedType?.display_name} readings found for the selected period.
            </p>
            {showFlaggedOnly && (
              <p className="text-sm text-gray-500 mb-4">
                Try turning off "Flagged Only" to see all readings.
              </p>
            )}
          </div>
        ) : (
          /* Initial State */
          <div className="text-center py-12 bg-gray-50 rounded-lg">
            <Calendar className="mx-auto h-12 w-12 text-gray-400 mb-4" />
            <h3 className="text-sm font-medium text-gray-900 mb-2">Select a measurement type</h3>
            <p className="text-sm text-gray-500">Choose a vital sign type to view your health trends.</p>
          </div>
        )}

        {/* Flagged Alert */}
        {stats.flagged > 0 && (
          <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-md">
            <div className="flex">
              <AlertTriangle className="h-5 w-5 text-yellow-400 mt-0.5" />
              <div className="ml-3">
                <p className="text-sm font-medium text-yellow-800">
                  {stats.flagged} of {stats.count} readings are flagged and may require attention.
                </p>
              </div>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default VitalSignsChart;