import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Activity, Plus, Filter, Search, Calendar, AlertTriangle, TrendingUp, Eye } from 'lucide-react';

export default function VitalSignsIndex({ vitalSignTypes = [] }) {
  const [vitalSigns, setVitalSigns] = useState([]);
  const [loading, setLoading] = useState(true);
  const [filters, setFilters] = useState({
    vital_sign_type_id: '',
    start_date: '',
    end_date: '',
    per_page: 15,
  });
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  const fetchVitalSigns = async (page = 1) => {
    setLoading(true);
    try {
      const queryParams = new URLSearchParams({
        ...filters,
        page: page.toString(),
      });

      // Remove empty values
      for (const [key, value] of [...queryParams.entries()]) {
        if (!value) {
          queryParams.delete(key);
        }
      }

      const response = await fetch(`/api/vital-signs?${queryParams.toString()}`, {
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch vital signs');
      }

      const data = await response.json();
      setVitalSigns(data.data || []);
      setPagination(data.meta || {});
    } catch (error) {
      console.error('Error fetching vital signs:', error);
      setVitalSigns([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchVitalSigns();
  }, [filters]);

  const handleFilterChange = (key, value) => {
    setFilters(prev => ({ ...prev, [key]: value }));
  };

  const formatValue = (record) => {
    if (record.value_secondary) {
      return `${record.value_primary}/${record.value_secondary}`;
    }
    return record.value_primary;
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const getMethodBadgeVariant = (method) => {
    switch (method) {
      case 'device': return 'default';
      case 'manual': return 'secondary';
      case 'estimated': return 'outline';
      default: return 'secondary';
    }
  };

  const clearFilters = () => {
    setFilters({
      vital_sign_type_id: '',
      start_date: '',
      end_date: '',
      per_page: 15,
    });
  };

  return (
    <AppLayout title="Vital Signs">
      <Head title="Vital Signs" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Vital Signs</h1>
            <p className="text-muted-foreground">
              View and manage all your health measurements
            </p>
          </div>
          <div className="flex gap-3">
            <Link href="/vital-signs/trends">
              <Button variant="outline">
                <TrendingUp className="h-4 w-4 mr-2" />
                View Trends
              </Button>
            </Link>
            <Link href="/vital-signs/create">
              <Button>
                <Plus className="h-4 w-4 mr-2" />
                Record New
              </Button>
            </Link>
          </div>
        </div>

        {/* Filters */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Filter className="h-4 w-4" />
              Filters
            </CardTitle>
          </CardHeader>
          <CardContent>
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div>
                <label className="text-sm font-medium mb-2 block">Measurement Type</label>
                <Select
                  value={filters.vital_sign_type_id}
                  onValueChange={(value) => handleFilterChange('vital_sign_type_id', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder="All types" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="">All types</SelectItem>
                    {vitalSignTypes.map((type) => (
                      <SelectItem key={type.id} value={type.id.toString()}>
                        {type.display_name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">Start Date</label>
                <Input
                  type="date"
                  value={filters.start_date}
                  onChange={(e) => handleFilterChange('start_date', e.target.value)}
                />
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">End Date</label>
                <Input
                  type="date"
                  value={filters.end_date}
                  onChange={(e) => handleFilterChange('end_date', e.target.value)}
                />
              </div>

              <div>
                <label className="text-sm font-medium mb-2 block">Per Page</label>
                <Select
                  value={filters.per_page.toString()}
                  onValueChange={(value) => handleFilterChange('per_page', parseInt(value))}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="10">10</SelectItem>
                    <SelectItem value="15">15</SelectItem>
                    <SelectItem value="25">25</SelectItem>
                    <SelectItem value="50">50</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="flex justify-end mt-4">
              <Button variant="outline" onClick={clearFilters}>
                Clear Filters
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Records List */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Activity className="h-4 w-4" />
              Your Measurements
              {pagination.total > 0 && (
                <Badge variant="secondary">
                  {pagination.total} total
                </Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                <p className="text-muted-foreground">Loading your vital signs...</p>
              </div>
            ) : vitalSigns.length === 0 ? (
              <div className="text-center py-12">
                <Activity className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <div className="text-lg font-medium mb-2">No measurements found</div>
                <p className="text-muted-foreground mb-4">
                  {Object.values(filters).some(Boolean)
                    ? "No measurements match your current filters. Try adjusting your search criteria."
                    : "You haven't recorded any vital signs yet. Start tracking your health today!"
                  }
                </p>
                <Link href="/vital-signs/create">
                  <Button>Record Your First Measurement</Button>
                </Link>
              </div>
            ) : (
              <div className="space-y-4">
                {vitalSigns.map((record) => (
                  <div
                    key={record.id}
                    className="border rounded-lg p-4 hover:shadow-md transition-shadow"
                  >
                    <div className="flex items-start justify-between">
                      <div className="flex-1">
                        <div className="flex items-center gap-3 mb-2">
                          <h3 className="font-semibold">
                            {record.vital_sign_type?.display_name}
                          </h3>
                          {record.is_flagged && (
                            <Badge variant="destructive" className="text-xs">
                              <AlertTriangle className="h-3 w-3 mr-1" />
                              Flagged
                            </Badge>
                          )}
                          <Badge variant={getMethodBadgeVariant(record.measurement_method)}>
                            {record.measurement_method}
                          </Badge>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                          <div>
                            <span className="text-muted-foreground">Value:</span>
                            <span className="ml-2 font-medium">
                              {formatValue(record)} {record.unit}
                            </span>
                          </div>
                          <div>
                            <span className="text-muted-foreground">Measured:</span>
                            <span className="ml-2">{formatDate(record.measured_at)}</span>
                          </div>
                          {record.device_name && (
                            <div>
                              <span className="text-muted-foreground">Device:</span>
                              <span className="ml-2">{record.device_name}</span>
                            </div>
                          )}
                        </div>

                        {record.notes && (
                          <div className="mt-3 text-sm">
                            <span className="text-muted-foreground">Notes:</span>
                            <span className="ml-2">{record.notes}</span>
                          </div>
                        )}

                        {record.flag_reason && (
                          <div className="mt-3 text-sm text-destructive">
                            <span className="font-medium">Alert:</span>
                            <span className="ml-2">{record.flag_reason}</span>
                          </div>
                        )}
                      </div>

                      <div className="flex gap-2">
                        <Button size="sm" variant="outline">
                          <Eye className="h-4 w-4" />
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}

                {/* Pagination */}
                {pagination.last_page > 1 && (
                  <div className="flex items-center justify-between pt-4">
                    <div className="text-sm text-muted-foreground">
                      Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
                      {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
                      {pagination.total} results
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.current_page === 1}
                        onClick={() => fetchVitalSigns(pagination.current_page - 1)}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.current_page === pagination.last_page}
                        onClick={() => fetchVitalSigns(pagination.current_page + 1)}
                      >
                        Next
                      </Button>
                    </div>
                  </div>
                )}
              </div>
            )}
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}