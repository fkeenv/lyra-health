import React, { useState, useMemo, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  TrendingUp,
  TrendingDown,
  Minus,
  Activity,
  AlertTriangle,
  Target,
  Calendar,
  BarChart3,
  Zap,
  CheckCircle,
  XCircle,
  Info
} from 'lucide-react';

const TrendAnalysis = ({
  data = [],
  vitalSignTypes = [],
  selectedTypeId = null,
  onTypeChange = null,
  period = 30,
  onPeriodChange = null,
  showInsights = true,
  showProjections = true,
  showGoals = false,
  goals = {},
  className = ""
}) => {
  const [analysisType, setAnalysisType] = useState('trend'); // trend, variability, correlation
  const [compareMode, setCompareMode] = useState(false);
  const [comparisonPeriod, setComparisonPeriod] = useState(7);

  // Filter data for selected type and period
  const filteredData = useMemo(() => {
    if (!selectedTypeId) return [];

    const cutoffDate = new Date();
    cutoffDate.setDate(cutoffDate.getDate() - period);

    return data
      .filter(d => d.vital_sign_type_id === parseInt(selectedTypeId))
      .filter(d => new Date(d.measured_at) >= cutoffDate)
      .sort((a, b) => new Date(a.measured_at) - new Date(b.measured_at));
  }, [data, selectedTypeId, period]);

  // Get selected vital sign type
  const selectedType = useMemo(() => {
    return vitalSignTypes.find(type => type.id === parseInt(selectedTypeId));
  }, [vitalSignTypes, selectedTypeId]);

  // Calculate comprehensive statistics
  const statistics = useMemo(() => {
    if (filteredData.length === 0) {
      return {
        count: 0,
        mean: 0,
        median: 0,
        stdDev: 0,
        min: 0,
        max: 0,
        range: 0,
        trend: 'stable',
        trendStrength: 0,
        variability: 'low',
        consistency: 0,
        improvement: 0,
        flaggedCount: 0,
        flaggedPercent: 0
      };
    }

    const values = filteredData.map(d => parseFloat(d.value_primary));
    const n = values.length;

    // Basic statistics
    const sum = values.reduce((a, b) => a + b, 0);
    const mean = sum / n;
    const sortedValues = [...values].sort((a, b) => a - b);
    const median = n % 2 === 0
      ? (sortedValues[n/2 - 1] + sortedValues[n/2]) / 2
      : sortedValues[Math.floor(n/2)];

    const variance = values.reduce((a, b) => a + Math.pow(b - mean, 2), 0) / n;
    const stdDev = Math.sqrt(variance);

    const min = Math.min(...values);
    const max = Math.max(...values);
    const range = max - min;

    // Trend analysis (linear regression)
    let trend = 'stable';
    let trendStrength = 0;

    if (n > 1) {
      const xValues = Array.from({length: n}, (_, i) => i + 1);
      const xMean = xValues.reduce((a, b) => a + b, 0) / n;

      const numerator = xValues.reduce((sum, x, i) => sum + (x - xMean) * (values[i] - mean), 0);
      const denominator = xValues.reduce((sum, x) => sum + Math.pow(x - xMean, 2), 0);

      if (denominator !== 0) {
        const slope = numerator / denominator;
        trendStrength = Math.abs(slope);

        if (Math.abs(slope) > 0.1) {
          trend = slope > 0 ? 'increasing' : 'decreasing';
        }
      }
    }

    // Variability assessment
    const coefficientOfVariation = mean !== 0 ? (stdDev / Math.abs(mean)) * 100 : 0;
    let variability = 'low';
    if (coefficientOfVariation > 20) variability = 'high';
    else if (coefficientOfVariation > 10) variability = 'medium';

    // Consistency (opposite of variability)
    const consistency = Math.max(0, 100 - coefficientOfVariation);

    // Compare with previous period for improvement
    let improvement = 0;
    if (compareMode && filteredData.length >= comparisonPeriod * 2) {
      const recentValues = values.slice(-comparisonPeriod);
      const previousValues = values.slice(-comparisonPeriod * 2, -comparisonPeriod);

      const recentMean = recentValues.reduce((a, b) => a + b, 0) / recentValues.length;
      const previousMean = previousValues.reduce((a, b) => a + b, 0) / previousValues.length;

      improvement = ((recentMean - previousMean) / previousMean) * 100;
    }

    // Flagged readings
    const flaggedCount = filteredData.filter(d => d.is_flagged).length;
    const flaggedPercent = (flaggedCount / n) * 100;

    return {
      count: n,
      mean: +mean.toFixed(2),
      median: +median.toFixed(2),
      stdDev: +stdDev.toFixed(2),
      min,
      max,
      range: +range.toFixed(2),
      trend,
      trendStrength: +trendStrength.toFixed(3),
      variability,
      consistency: +consistency.toFixed(1),
      improvement: +improvement.toFixed(1),
      flaggedCount,
      flaggedPercent: +flaggedPercent.toFixed(1)
    };
  }, [filteredData, compareMode, comparisonPeriod]);

  // Generate insights based on analysis
  const insights = useMemo(() => {
    if (!selectedType || statistics.count === 0) return [];

    const insights = [];

    // Trend insights
    if (statistics.trend === 'increasing') {
      insights.push({
        type: statistics.trendStrength > 1 ? 'warning' : 'info',
        title: 'Upward Trend Detected',
        message: `Your ${selectedType.display_name} has been trending upward over the last ${period} days. ${statistics.trendStrength > 1 ? 'This is a significant change that may need attention.' : 'Monitor this trend closely.'}`
      });
    } else if (statistics.trend === 'decreasing') {
      insights.push({
        type: selectedType.name === 'blood_pressure' ? 'positive' : 'info',
        title: 'Downward Trend Detected',
        message: `Your ${selectedType.display_name} has been trending downward over the last ${period} days. ${selectedType.name === 'blood_pressure' ? 'This could be a positive improvement!' : 'Monitor this trend closely.'}`
      });
    }

    // Variability insights
    if (statistics.variability === 'high') {
      insights.push({
        type: 'warning',
        title: 'High Variability',
        message: `Your ${selectedType.display_name} readings show high variability. Consistency in measurements can help with better health management.`
      });
    } else if (statistics.consistency > 80) {
      insights.push({
        type: 'positive',
        title: 'Consistent Readings',
        message: `Great! Your ${selectedType.display_name} readings are very consistent, which indicates good measurement habits.`
      });
    }

    // Normal range insights
    if (selectedType.normal_range_min && selectedType.normal_range_max) {
      const inRangeCount = filteredData.filter(d => {
        const value = parseFloat(d.value_primary);
        return value >= selectedType.normal_range_min && value <= selectedType.normal_range_max;
      }).length;

      const inRangePercent = (inRangeCount / statistics.count) * 100;

      if (inRangePercent >= 90) {
        insights.push({
          type: 'positive',
          title: 'Excellent Range Compliance',
          message: `${inRangePercent.toFixed(0)}% of your readings are within the normal range. Keep up the great work!`
        });
      } else if (inRangePercent < 70) {
        insights.push({
          type: 'warning',
          title: 'Range Compliance Concern',
          message: `Only ${inRangePercent.toFixed(0)}% of your readings are within the normal range. Consider discussing this with your healthcare provider.`
        });
      }
    }

    // Flagged readings insight
    if (statistics.flaggedPercent > 30) {
      insights.push({
        type: 'alert',
        title: 'High Number of Flagged Readings',
        message: `${statistics.flaggedPercent}% of your readings are flagged. This suggests values outside normal ranges that may need medical attention.`
      });
    }

    // Goal insights
    if (showGoals && goals[selectedType.name]) {
      const goal = goals[selectedType.name];
      const recentMean = statistics.mean;

      if (Math.abs(recentMean - goal.target) <= goal.tolerance) {
        insights.push({
          type: 'positive',
          title: 'Goal Achievement',
          message: `You're meeting your ${selectedType.display_name} goal! Your average is ${recentMean} ${selectedType.unit_primary}, which is within your target range.`
        });
      } else {
        const distance = Math.abs(recentMean - goal.target);
        insights.push({
          type: 'info',
          title: 'Goal Progress',
          message: `You're ${distance.toFixed(1)} ${selectedType.unit_primary} away from your ${selectedType.display_name} goal of ${goal.target} ${selectedType.unit_primary}.`
        });
      }
    }

    return insights.slice(0, 4); // Limit to 4 insights
  }, [statistics, selectedType, period, filteredData, showGoals, goals]);

  // Generate simple projections
  const projection = useMemo(() => {
    if (!showProjections || statistics.count < 5 || statistics.trend === 'stable') {
      return null;
    }

    const projectedChange = statistics.trendStrength * 7; // 7 days ahead
    const projectedValue = statistics.mean + (statistics.trend === 'increasing' ? projectedChange : -projectedChange);

    return {
      value: +projectedValue.toFixed(1),
      change: +projectedChange.toFixed(1),
      direction: statistics.trend,
      confidence: statistics.count > 10 ? 'high' : statistics.count > 7 ? 'medium' : 'low'
    };
  }, [showProjections, statistics]);

  const getInsightIcon = (type) => {
    switch (type) {
      case 'positive': return <CheckCircle className="h-4 w-4 text-green-500" />;
      case 'warning': return <AlertTriangle className="h-4 w-4 text-yellow-500" />;
      case 'alert': return <XCircle className="h-4 w-4 text-red-500" />;
      default: return <Info className="h-4 w-4 text-blue-500" />;
    }
  };

  const getInsightColors = (type) => {
    switch (type) {
      case 'positive': return 'bg-green-50 border-green-200';
      case 'warning': return 'bg-yellow-50 border-yellow-200';
      case 'alert': return 'bg-red-50 border-red-200';
      default: return 'bg-blue-50 border-blue-200';
    }
  };

  const getTrendIcon = (trend) => {
    switch (trend) {
      case 'increasing': return <TrendingUp className="h-5 w-5 text-orange-500" />;
      case 'decreasing': return <TrendingDown className="h-5 w-5 text-blue-500" />;
      default: return <Minus className="h-5 w-5 text-gray-500" />;
    }
  };

  return (
    <Card className={className}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <BarChart3 className="h-5 w-5" />
              Trend Analysis
            </CardTitle>
            <CardDescription>
              {selectedType
                ? `Advanced analysis of your ${selectedType.display_name} patterns`
                : 'Select a vital sign type to view detailed analysis'
              }
            </CardDescription>
          </div>

          <div className="flex items-center gap-3">
            {/* Analysis Type */}
            <Select value={analysisType} onValueChange={setAnalysisType}>
              <SelectTrigger className="w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="trend">Trend</SelectItem>
                <SelectItem value="variability">Variability</SelectItem>
                <SelectItem value="patterns">Patterns</SelectItem>
              </SelectContent>
            </Select>

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

            {/* Compare toggle */}
            <Button
              variant={compareMode ? "default" : "outline"}
              size="sm"
              onClick={() => setCompareMode(!compareMode)}
            >
              Compare
            </Button>
          </div>
        </div>

        {/* Type Selector */}
        {!selectedTypeId && onTypeChange && (
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
        )}
      </CardHeader>

      <CardContent>
        {filteredData.length === 0 ? (
          <div className="text-center py-8">
            <Activity className="h-12 w-12 text-gray-400 mx-auto mb-4" />
            <h3 className="text-sm font-medium text-gray-900 mb-2">No data available</h3>
            <p className="text-sm text-gray-500">
              {!selectedTypeId
                ? "Select a vital sign type to view analysis."
                : `No ${selectedType?.display_name} readings found for the selected period.`
              }
            </p>
          </div>
        ) : (
          <div className="space-y-6">
            {/* Key Statistics */}
            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
              <div className="text-center p-4 bg-gray-50 rounded-lg">
                <div className="text-2xl font-bold text-gray-900">{statistics.count}</div>
                <div className="text-xs text-gray-500">Total Readings</div>
              </div>

              <div className="text-center p-4 bg-blue-50 rounded-lg">
                <div className="text-2xl font-bold text-blue-600">{statistics.mean}</div>
                <div className="text-xs text-gray-500">Average Value</div>
              </div>

              <div className="text-center p-4 bg-green-50 rounded-lg">
                <div className="flex items-center justify-center mb-1">
                  {getTrendIcon(statistics.trend)}
                </div>
                <div className="text-xs text-gray-500 capitalize">{statistics.trend}</div>
              </div>

              <div className="text-center p-4 bg-purple-50 rounded-lg">
                <div className="text-2xl font-bold text-purple-600">{statistics.consistency}%</div>
                <div className="text-xs text-gray-500">Consistency</div>
              </div>
            </div>

            {/* Detailed Analysis */}
            {analysisType === 'trend' && (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-base">Trend Details</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Direction:</span>
                      <Badge variant="outline" className="capitalize">
                        {statistics.trend}
                      </Badge>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Strength:</span>
                      <span className="text-sm font-medium">{statistics.trendStrength.toFixed(3)}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Range:</span>
                      <span className="text-sm font-medium">
                        {statistics.min} - {statistics.max} {selectedType?.unit_primary}
                      </span>
                    </div>
                    {compareMode && (
                      <div className="flex justify-between">
                        <span className="text-sm text-gray-500">vs Previous:</span>
                        <span className={`text-sm font-medium ${
                          statistics.improvement > 0 ? 'text-green-600' :
                          statistics.improvement < 0 ? 'text-red-600' : 'text-gray-600'
                        }`}>
                          {statistics.improvement > 0 ? '+' : ''}{statistics.improvement}%
                        </span>
                      </div>
                    )}
                  </CardContent>
                </Card>

                <Card>
                  <CardHeader className="pb-3">
                    <CardTitle className="text-base">Statistical Summary</CardTitle>
                  </CardHeader>
                  <CardContent className="space-y-3">
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Median:</span>
                      <span className="text-sm font-medium">{statistics.median} {selectedType?.unit_primary}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Std Deviation:</span>
                      <span className="text-sm font-medium">{statistics.stdDev}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Variability:</span>
                      <Badge variant="outline" className={
                        statistics.variability === 'high' ? 'border-red-300 text-red-700' :
                        statistics.variability === 'medium' ? 'border-yellow-300 text-yellow-700' :
                        'border-green-300 text-green-700'
                      }>
                        {statistics.variability}
                      </Badge>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-sm text-gray-500">Flagged:</span>
                      <span className="text-sm font-medium text-red-600">
                        {statistics.flaggedCount} ({statistics.flaggedPercent}%)
                      </span>
                    </div>
                  </CardContent>
                </Card>
              </div>
            )}

            {/* Projection */}
            {projection && (
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-base flex items-center gap-2">
                    <Zap className="h-4 w-4" />
                    7-Day Projection
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="flex items-center justify-between">
                    <div>
                      <div className="text-lg font-semibold">
                        {projection.value} {selectedType?.unit_primary}
                      </div>
                      <div className="text-sm text-gray-500">
                        {projection.direction === 'increasing' ? '+' : '-'}{projection.change} projected change
                      </div>
                    </div>
                    <Badge variant="outline" className={
                      projection.confidence === 'high' ? 'border-green-300 text-green-700' :
                      projection.confidence === 'medium' ? 'border-yellow-300 text-yellow-700' :
                      'border-red-300 text-red-700'
                    }>
                      {projection.confidence} confidence
                    </Badge>
                  </div>
                </CardContent>
              </Card>
            )}

            {/* Insights */}
            {showInsights && insights.length > 0 && (
              <div>
                <h3 className="text-base font-semibold mb-4 flex items-center gap-2">
                  <Target className="h-4 w-4" />
                  Health Insights
                </h3>
                <div className="space-y-3">
                  {insights.map((insight, index) => (
                    <div
                      key={index}
                      className={`p-4 border rounded-lg ${getInsightColors(insight.type)}`}
                    >
                      <div className="flex items-start gap-3">
                        {getInsightIcon(insight.type)}
                        <div>
                          <h4 className="font-medium text-sm mb-1">{insight.title}</h4>
                          <p className="text-sm text-gray-700">{insight.message}</p>
                        </div>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            {/* Goals Progress */}
            {showGoals && goals[selectedType?.name] && (
              <Card>
                <CardHeader className="pb-3">
                  <CardTitle className="text-base flex items-center gap-2">
                    <Target className="h-4 w-4" />
                    Goal Progress
                  </CardTitle>
                </CardHeader>
                <CardContent>
                  <div className="space-y-3">
                    <div className="flex justify-between items-center">
                      <span className="text-sm text-gray-500">Target:</span>
                      <span className="font-medium">{goals[selectedType.name].target} {selectedType?.unit_primary}</span>
                    </div>
                    <div className="flex justify-between items-center">
                      <span className="text-sm text-gray-500">Current Average:</span>
                      <span className="font-medium">{statistics.mean} {selectedType?.unit_primary}</span>
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                      <div
                        className="bg-blue-600 h-2 rounded-full transition-all"
                        style={{
                          width: `${Math.min(100, Math.max(0, (statistics.mean / goals[selectedType.name].target) * 100))}%`
                        }}
                      ></div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default TrendAnalysis;