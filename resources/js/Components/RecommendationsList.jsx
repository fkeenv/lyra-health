import React, { useState, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  MessageSquare,
  AlertTriangle,
  CheckCircle,
  Clock,
  Search,
  Filter,
  Lightbulb,
  ShieldAlert,
  Info,
  Eye,
  X,
  ExternalLink,
  Calendar,
  TrendingUp,
  ChevronDown,
  ChevronUp
} from 'lucide-react';

const RecommendationsList = ({
  recommendations = [],
  onMarkAsRead = null,
  onDismiss = null,
  onTakeAction = null,
  showFilters = true,
  showSearch = true,
  showStats = true,
  compact = false,
  maxHeight = null,
  className = ""
}) => {
  const [searchTerm, setSearchTerm] = useState('');
  const [typeFilter, setTypeFilter] = useState('all');
  const [statusFilter, setStatusFilter] = useState('all');
  const [severityFilter, setSeverityFilter] = useState('all');
  const [expandedItems, setExpandedItems] = useState(new Set());

  // Filter recommendations
  const filteredRecommendations = useMemo(() => {
    return recommendations.filter(rec => {
      const matchesSearch = searchTerm === '' ||
        rec.title.toLowerCase().includes(searchTerm.toLowerCase()) ||
        rec.message.toLowerCase().includes(searchTerm.toLowerCase());

      const matchesType = typeFilter === 'all' || rec.recommendation_type === typeFilter;

      const matchesStatus = statusFilter === 'all' || (() => {
        switch (statusFilter) {
          case 'unread': return !rec.read_at;
          case 'read': return rec.read_at && !rec.dismissed_at;
          case 'dismissed': return rec.dismissed_at;
          case 'expired': return rec.expires_at && new Date(rec.expires_at) < new Date();
          case 'action_required': return rec.action_required;
          default: return true;
        }
      })();

      const matchesSeverity = severityFilter === 'all' || rec.severity === severityFilter;

      return matchesSearch && matchesType && matchesStatus && matchesSeverity;
    });
  }, [recommendations, searchTerm, typeFilter, statusFilter, severityFilter]);

  // Calculate statistics
  const stats = useMemo(() => {
    return {
      total: recommendations.length,
      unread: recommendations.filter(r => !r.read_at).length,
      actionRequired: recommendations.filter(r => r.action_required && !r.dismissed_at).length,
      critical: recommendations.filter(r => r.severity === 'critical' && !r.dismissed_at).length,
      dismissed: recommendations.filter(r => r.dismissed_at).length,
    };
  }, [recommendations]);

  // Get recommendation type configuration
  const getTypeConfig = (type) => {
    switch (type) {
      case 'alert':
        return {
          icon: ShieldAlert,
          color: 'text-red-600',
          bgColor: 'bg-red-50',
          borderColor: 'border-red-200',
          badgeColor: 'bg-red-100 text-red-800'
        };
      case 'warning':
        return {
          icon: AlertTriangle,
          color: 'text-yellow-600',
          bgColor: 'bg-yellow-50',
          borderColor: 'border-yellow-200',
          badgeColor: 'bg-yellow-100 text-yellow-800'
        };
      case 'suggestion':
        return {
          icon: Lightbulb,
          color: 'text-blue-600',
          bgColor: 'bg-blue-50',
          borderColor: 'border-blue-200',
          badgeColor: 'bg-blue-100 text-blue-800'
        };
      case 'congratulation':
        return {
          icon: CheckCircle,
          color: 'text-green-600',
          bgColor: 'bg-green-50',
          borderColor: 'border-green-200',
          badgeColor: 'bg-green-100 text-green-800'
        };
      default:
        return {
          icon: Info,
          color: 'text-gray-600',
          bgColor: 'bg-gray-50',
          borderColor: 'border-gray-200',
          badgeColor: 'bg-gray-100 text-gray-800'
        };
    }
  };

  // Get severity styling
  const getSeverityStyle = (severity) => {
    switch (severity) {
      case 'critical':
        return 'ring-2 ring-red-500 ring-opacity-20';
      case 'high':
        return 'ring-1 ring-red-400 ring-opacity-20';
      case 'medium':
        return 'ring-1 ring-yellow-400 ring-opacity-20';
      default:
        return '';
    }
  };

  // Handle actions
  const handleMarkAsRead = async (recommendation) => {
    if (onMarkAsRead) {
      await onMarkAsRead(recommendation);
    } else {
      router.post(`/api/recommendations/${recommendation.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          // Update local state if needed
        }
      });
    }
  };

  const handleDismiss = async (recommendation) => {
    if (onDismiss) {
      await onDismiss(recommendation);
    } else {
      router.post(`/api/recommendations/${recommendation.id}/dismiss`, {}, {
        preserveScroll: true,
        onSuccess: () => {
          // Update local state if needed
        }
      });
    }
  };

  const handleTakeAction = (recommendation) => {
    if (onTakeAction) {
      onTakeAction(recommendation);
    } else {
      // Default action - could navigate to relevant page
      if (recommendation.vital_signs_record) {
        router.visit('/vital-signs');
      }
    }
  };

  const toggleExpanded = (id) => {
    setExpandedItems(prev => {
      const newSet = new Set(prev);
      if (newSet.has(id)) {
        newSet.delete(id);
      } else {
        newSet.add(id);
      }
      return newSet;
    });
  };

  const formatDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  };

  const isExpired = (recommendation) => {
    return recommendation.expires_at && new Date(recommendation.expires_at) < new Date();
  };

  return (
    <Card className={className}>
      <CardHeader>
        <div className="flex items-center justify-between">
          <div>
            <CardTitle className="flex items-center gap-2">
              <MessageSquare className="h-5 w-5" />
              Health Recommendations
            </CardTitle>
            <CardDescription>
              Personalized advice based on your health data
            </CardDescription>
          </div>

          {/* Quick Stats */}
          {showStats && (
            <div className="flex items-center gap-4 text-sm">
              <div className="flex items-center gap-1">
                <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                <span>{stats.unread} new</span>
              </div>
              {stats.actionRequired > 0 && (
                <div className="flex items-center gap-1">
                  <AlertTriangle className="h-3 w-3 text-orange-500" />
                  <span>{stats.actionRequired} require action</span>
                </div>
              )}
              {stats.critical > 0 && (
                <div className="flex items-center gap-1">
                  <ShieldAlert className="h-3 w-3 text-red-500" />
                  <span>{stats.critical} critical</span>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Filters */}
        {showFilters && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 pt-4">
            {showSearch && (
              <div className="relative">
                <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                <Input
                  placeholder="Search recommendations..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="pl-10"
                />
              </div>
            )}

            <Select value={typeFilter} onValueChange={setTypeFilter}>
              <SelectTrigger>
                <SelectValue placeholder="All types" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Types</SelectItem>
                <SelectItem value="alert">Alerts</SelectItem>
                <SelectItem value="warning">Warnings</SelectItem>
                <SelectItem value="suggestion">Suggestions</SelectItem>
                <SelectItem value="congratulation">Congratulations</SelectItem>
              </SelectContent>
            </Select>

            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger>
                <SelectValue placeholder="All status" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="unread">Unread</SelectItem>
                <SelectItem value="read">Read</SelectItem>
                <SelectItem value="action_required">Action Required</SelectItem>
                <SelectItem value="dismissed">Dismissed</SelectItem>
                <SelectItem value="expired">Expired</SelectItem>
              </SelectContent>
            </Select>

            <Select value={severityFilter} onValueChange={setSeverityFilter}>
              <SelectTrigger>
                <SelectValue placeholder="All severity" />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Severity</SelectItem>
                <SelectItem value="low">Low</SelectItem>
                <SelectItem value="medium">Medium</SelectItem>
                <SelectItem value="high">High</SelectItem>
                <SelectItem value="critical">Critical</SelectItem>
              </SelectContent>
            </Select>
          </div>
        )}
      </CardHeader>

      <CardContent>
        <div className={maxHeight ? `max-h-${maxHeight} overflow-y-auto` : ''}>
          {filteredRecommendations.length === 0 ? (
            <div className="text-center py-8">
              <MessageSquare className="h-12 w-12 text-gray-400 mx-auto mb-4" />
              <h3 className="text-sm font-medium text-gray-900 mb-2">No recommendations found</h3>
              <p className="text-sm text-gray-500">
                {recommendations.length === 0
                  ? "You don't have any health recommendations yet."
                  : "No recommendations match your current filters."
                }
              </p>
            </div>
          ) : (
            <div className="space-y-4">
              {filteredRecommendations.map((recommendation) => {
                const config = getTypeConfig(recommendation.recommendation_type);
                const Icon = config.icon;
                const isRead = !!recommendation.read_at;
                const isDismissed = !!recommendation.dismissed_at;
                const isExpiredRec = isExpired(recommendation);
                const isExpanded = expandedItems.has(recommendation.id);
                const hasMetadata = recommendation.metadata && Object.keys(recommendation.metadata).length > 0;

                return (
                  <div
                    key={recommendation.id}
                    className={`
                      border rounded-lg transition-all duration-200 hover:shadow-md
                      ${config.borderColor} ${config.bgColor}
                      ${getSeverityStyle(recommendation.severity)}
                      ${isDismissed ? 'opacity-60' : ''}
                      ${!isRead && !isDismissed ? 'ring-2 ring-blue-200 ring-opacity-30' : ''}
                      ${compact ? 'p-4' : 'p-6'}
                    `}
                  >
                    <div className="flex items-start gap-4">
                      {/* Icon */}
                      <div className="flex-shrink-0">
                        <Icon className={`h-5 w-5 ${config.color}`} />
                      </div>

                      {/* Content */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-start justify-between mb-2">
                          <div className="flex items-center gap-2">
                            <h3 className={`font-medium ${compact ? 'text-sm' : 'text-base'} text-gray-900`}>
                              {recommendation.title}
                            </h3>
                            <Badge variant="outline" className={`text-xs ${config.badgeColor}`}>
                              {recommendation.recommendation_type}
                            </Badge>
                            {recommendation.severity === 'critical' || recommendation.severity === 'high' ? (
                              <Badge variant="destructive" className="text-xs">
                                {recommendation.severity.toUpperCase()}
                              </Badge>
                            ) : null}
                          </div>

                          {/* Status indicators */}
                          <div className="flex items-center gap-2">
                            {!isRead && !isDismissed && (
                              <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                            )}
                            {isDismissed && (
                              <Badge variant="secondary" className="text-xs">Dismissed</Badge>
                            )}
                            {isExpiredRec && (
                              <Badge variant="outline" className="text-xs">Expired</Badge>
                            )}
                          </div>
                        </div>

                        {/* Message */}
                        <p className={`${config.color} ${compact ? 'text-sm' : 'text-base'} mb-3`}>
                          {recommendation.message}
                        </p>

                        {/* Related vital sign */}
                        {recommendation.vital_signs_record && (
                          <div className="mb-3 p-2 bg-white bg-opacity-60 rounded border border-opacity-50">
                            <div className="flex items-center text-sm text-gray-600">
                              <TrendingUp className="h-4 w-4 mr-2" />
                              <span className="font-medium">Related to:</span>
                              <span className="ml-2">
                                {recommendation.vital_signs_record.vital_sign_type?.display_name}
                              </span>
                              <span className="ml-2 font-mono">
                                {recommendation.vital_signs_record.value_primary}
                                {recommendation.vital_signs_record.value_secondary &&
                                  `/${recommendation.vital_signs_record.value_secondary}`}
                                {' '}{recommendation.vital_signs_record.unit}
                              </span>
                              <span className="ml-2 text-xs">
                                {formatDate(recommendation.vital_signs_record.measured_at)}
                              </span>
                            </div>
                          </div>
                        )}

                        {/* Expandable metadata */}
                        {hasMetadata && (
                          <div className="mb-3">
                            <button
                              onClick={() => toggleExpanded(recommendation.id)}
                              className="flex items-center text-xs text-gray-500 hover:text-gray-700"
                            >
                              {isExpanded ? <ChevronUp className="h-3 w-3 mr-1" /> : <ChevronDown className="h-3 w-3 mr-1" />}
                              Additional Details
                            </button>

                            {isExpanded && (
                              <div className="mt-2 p-2 bg-white bg-opacity-60 rounded border">
                                <dl className="text-xs space-y-1">
                                  {Object.entries(recommendation.metadata).map(([key, value]) => (
                                    <div key={key} className="flex">
                                      <dt className="font-medium text-gray-600 mr-2">
                                        {key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}:
                                      </dt>
                                      <dd className="text-gray-800">
                                        {Array.isArray(value) ? value.join(', ') : String(value)}
                                      </dd>
                                    </div>
                                  ))}
                                </dl>
                              </div>
                            )}
                          </div>
                        )}

                        {/* Footer */}
                        <div className="flex items-center justify-between">
                          <div className="flex items-center text-xs text-gray-500 space-x-4">
                            <div className="flex items-center">
                              <Calendar className="h-3 w-3 mr-1" />
                              {formatDate(recommendation.created_at)}
                            </div>

                            {recommendation.expires_at && (
                              <div className={`flex items-center ${isExpiredRec ? 'text-red-500' : ''}`}>
                                <Clock className="h-3 w-3 mr-1" />
                                {isExpiredRec ? 'Expired' : 'Expires'} {formatDate(recommendation.expires_at)}
                              </div>
                            )}

                            {recommendation.action_required && !isDismissed && (
                              <Badge variant="outline" className="text-orange-600 border-orange-300">
                                Action Required
                              </Badge>
                            )}

                            {isRead && (
                              <div className="flex items-center text-green-600">
                                <CheckCircle className="h-3 w-3 mr-1" />
                                Read
                              </div>
                            )}
                          </div>

                          {/* Actions */}
                          {!isDismissed && !isExpiredRec && (
                            <div className="flex items-center gap-2">
                              {!isRead && (
                                <Button
                                  size="sm"
                                  variant="outline"
                                  onClick={() => handleMarkAsRead(recommendation)}
                                >
                                  <Eye className="h-3 w-3 mr-1" />
                                  Mark Read
                                </Button>
                              )}

                              {recommendation.action_required && (
                                <Button
                                  size="sm"
                                  onClick={() => handleTakeAction(recommendation)}
                                >
                                  <ExternalLink className="h-3 w-3 mr-1" />
                                  Take Action
                                </Button>
                              )}

                              <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => handleDismiss(recommendation)}
                              >
                                <X className="h-3 w-3 mr-1" />
                                Dismiss
                              </Button>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>

        {/* Footer stats */}
        {showStats && filteredRecommendations.length > 0 && (
          <div className="mt-6 pt-4 border-t border-gray-200">
            <div className="flex items-center justify-between text-sm text-gray-500">
              <span>
                Showing {filteredRecommendations.length} of {recommendations.length} recommendations
              </span>
              <div className="flex items-center gap-4">
                <span>{stats.unread} unread</span>
                <span>{stats.dismissed} dismissed</span>
              </div>
            </div>
          </div>
        )}
      </CardContent>
    </Card>
  );
};

export default RecommendationsList;