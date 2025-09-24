import { useState, useEffect } from 'react';
import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/Components/ui/select';
import {
  Heart,
  AlertTriangle,
  Info,
  CheckCircle,
  Clock,
  Filter,
  TrendingUp,
  Activity,
  Shield
} from 'lucide-react';

export default function RecommendationsIndex() {
  const [recommendations, setRecommendations] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filterType, setFilterType] = useState('all');
  const [filterSeverity, setFilterSeverity] = useState('all');

  useEffect(() => {
    loadRecommendations();
  }, []);

  const loadRecommendations = async () => {
    setLoading(true);
    try {
      const response = await fetch('/api/recommendations', {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to load recommendations');
      }

      const data = await response.json();
      setRecommendations(data.data || []);
    } catch (error) {
      console.error('Error loading recommendations:', error);
      setRecommendations([]);
    } finally {
      setLoading(false);
    }
  };

  const markAsRead = async (recommendationId) => {
    try {
      const response = await fetch(`/api/recommendations/${recommendationId}/mark-read`, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (response.ok) {
        setRecommendations(prev =>
          prev.map(rec =>
            rec.id === recommendationId
              ? { ...rec, is_read: true, read_at: new Date().toISOString() }
              : rec
          )
        );
      }
    } catch (error) {
      console.error('Error marking recommendation as read:', error);
    }
  };

  const getSeverityIcon = (severity) => {
    switch (severity) {
      case 'high':
        return <AlertTriangle className="h-4 w-4 text-red-600" />;
      case 'medium':
        return <Info className="h-4 w-4 text-orange-600" />;
      case 'low':
        return <CheckCircle className="h-4 w-4 text-blue-600" />;
      default:
        return <Info className="h-4 w-4 text-gray-600" />;
    }
  };

  const getSeverityColor = (severity) => {
    switch (severity) {
      case 'high':
        return 'bg-red-100 text-red-800 border-red-200';
      case 'medium':
        return 'bg-orange-100 text-orange-800 border-orange-200';
      case 'low':
        return 'bg-blue-100 text-blue-800 border-blue-200';
      default:
        return 'bg-gray-100 text-gray-800 border-gray-200';
    }
  };

  const getTypeIcon = (type) => {
    switch (type) {
      case 'exercise':
        return <Activity className="h-4 w-4" />;
      case 'nutrition':
        return <Heart className="h-4 w-4" />;
      case 'monitoring':
        return <TrendingUp className="h-4 w-4" />;
      case 'lifestyle':
        return <Shield className="h-4 w-4" />;
      default:
        return <Info className="h-4 w-4" />;
    }
  };

  const filteredRecommendations = recommendations.filter(rec => {
    const typeMatch = filterType === 'all' || rec.recommendation_type === filterType;
    const severityMatch = filterSeverity === 'all' || rec.severity === filterSeverity;
    return typeMatch && severityMatch;
  });

  const unreadCount = recommendations.filter(rec => !rec.is_read).length;

  // Mock data for demonstration if no real data
  const mockRecommendations = [
    {
      id: 1,
      title: 'Blood Pressure Monitoring',
      message: 'Your recent blood pressure readings are slightly elevated. Consider monitoring more frequently and consult with your healthcare provider.',
      recommendation_type: 'monitoring',
      severity: 'medium',
      is_read: false,
      created_at: new Date().toISOString(),
    },
    {
      id: 2,
      title: 'Regular Exercise',
      message: 'Based on your activity levels, incorporating 30 minutes of daily exercise could help improve your cardiovascular health.',
      recommendation_type: 'exercise',
      severity: 'low',
      is_read: false,
      created_at: new Date(Date.now() - 86400000).toISOString(),
    },
    {
      id: 3,
      title: 'Heart Rate Variability',
      message: 'Your heart rate shows good variability. Continue your current exercise routine to maintain cardiovascular fitness.',
      recommendation_type: 'lifestyle',
      severity: 'low',
      is_read: true,
      created_at: new Date(Date.now() - 172800000).toISOString(),
    },
  ];

  const displayRecommendations = recommendations.length > 0 ? filteredRecommendations : mockRecommendations;

  return (
    <AppLayout title="Health Recommendations">
      <Head title="Health Recommendations" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Health Recommendations</h1>
            <p className="text-muted-foreground">
              Personalized health insights and suggestions based on your vital signs
            </p>
          </div>
          <div className="flex items-center space-x-2">
            {unreadCount > 0 && (
              <Badge variant="secondary" className="bg-primary text-primary-foreground">
                {unreadCount} unread
              </Badge>
            )}
          </div>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Filter className="h-5 w-5" />
              Filter Recommendations
            </CardTitle>
            <CardDescription>
              Filter by recommendation type and severity level
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-medium">Type</label>
                <Select value={filterType} onValueChange={setFilterType}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Types</SelectItem>
                    <SelectItem value="exercise">Exercise</SelectItem>
                    <SelectItem value="nutrition">Nutrition</SelectItem>
                    <SelectItem value="monitoring">Monitoring</SelectItem>
                    <SelectItem value="lifestyle">Lifestyle</SelectItem>
                  </SelectContent>
                </Select>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Severity</label>
                <Select value={filterSeverity} onValueChange={setFilterSeverity}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Severities</SelectItem>
                    <SelectItem value="high">High Priority</SelectItem>
                    <SelectItem value="medium">Medium Priority</SelectItem>
                    <SelectItem value="low">Low Priority</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Recommendations List */}
        <div className="space-y-4">
          {loading ? (
            <Card>
              <CardContent className="text-center py-12">
                <div className="text-muted-foreground">Loading recommendations...</div>
              </CardContent>
            </Card>
          ) : displayRecommendations.length > 0 ? (
            displayRecommendations.map((recommendation) => (
              <Card
                key={recommendation.id}
                className={`transition-all hover:shadow-md ${
                  !recommendation.is_read ? 'border-l-4 border-l-primary' : ''
                }`}
              >
                <CardHeader className="pb-3">
                  <div className="flex items-start justify-between">
                    <div className="flex items-center space-x-3">
                      <div className="p-2 rounded-full bg-muted">
                        {getTypeIcon(recommendation.recommendation_type)}
                      </div>
                      <div>
                        <CardTitle className="text-lg">
                          {recommendation.title}
                        </CardTitle>
                        <div className="flex items-center space-x-2 mt-1">
                          <Badge
                            variant="outline"
                            className={getSeverityColor(recommendation.severity)}
                          >
                            {getSeverityIcon(recommendation.severity)}
                            <span className="ml-1 capitalize">{recommendation.severity}</span>
                          </Badge>
                          <Badge variant="secondary" className="capitalize">
                            {recommendation.recommendation_type}
                          </Badge>
                          {!recommendation.is_read && (
                            <Badge className="bg-primary text-primary-foreground">
                              New
                            </Badge>
                          )}
                        </div>
                      </div>
                    </div>
                    <div className="flex items-center space-x-2 text-sm text-muted-foreground">
                      <Clock className="h-4 w-4" />
                      <span>
                        {new Date(recommendation.created_at).toLocaleDateString()}
                      </span>
                    </div>
                  </div>
                </CardHeader>
                <CardContent>
                  <p className="text-muted-foreground mb-4">
                    {recommendation.message}
                  </p>
                  <div className="flex justify-between items-center">
                    <div className="text-sm text-muted-foreground">
                      {recommendation.is_read && recommendation.read_at && (
                        <span>Read on {new Date(recommendation.read_at).toLocaleDateString()}</span>
                      )}
                    </div>
                    <div className="flex space-x-2">
                      {!recommendation.is_read && (
                        <Button
                          variant="outline"
                          size="sm"
                          onClick={() => markAsRead(recommendation.id)}
                        >
                          Mark as Read
                        </Button>
                      )}
                      <Button variant="outline" size="sm">
                        Learn More
                      </Button>
                    </div>
                  </div>
                </CardContent>
              </Card>
            ))
          ) : (
            <Card>
              <CardContent className="text-center py-12">
                <Heart className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <div className="text-lg font-medium mb-2">No recommendations available</div>
                <p className="text-muted-foreground mb-4">
                  Start recording your vital signs to receive personalized health recommendations.
                </p>
                <Button onClick={() => window.location.href = '/vital-signs/create'}>
                  Record Vital Signs
                </Button>
              </CardContent>
            </Card>
          )}
        </div>

        {/* Summary Card */}
        {displayRecommendations.length > 0 && (
          <Card className="bg-muted/50">
            <CardHeader>
              <CardTitle className="text-lg">Recommendation Summary</CardTitle>
            </CardHeader>
            <CardContent>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                <div>
                  <div className="text-2xl font-bold text-red-600">
                    {displayRecommendations.filter(r => r.severity === 'high').length}
                  </div>
                  <div className="text-sm text-muted-foreground">High Priority</div>
                </div>
                <div>
                  <div className="text-2xl font-bold text-orange-600">
                    {displayRecommendations.filter(r => r.severity === 'medium').length}
                  </div>
                  <div className="text-sm text-muted-foreground">Medium Priority</div>
                </div>
                <div>
                  <div className="text-2xl font-bold text-blue-600">
                    {displayRecommendations.filter(r => r.severity === 'low').length}
                  </div>
                  <div className="text-sm text-muted-foreground">Low Priority</div>
                </div>
                <div>
                  <div className="text-2xl font-bold text-green-600">
                    {displayRecommendations.filter(r => r.is_read).length}
                  </div>
                  <div className="text-sm text-muted-foreground">Completed</div>
                </div>
              </div>
            </CardContent>
          </Card>
        )}
      </div>
    </AppLayout>
  );
}