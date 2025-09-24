import { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  Shield,
  Plus,
  Search,
  Filter,
  UserCheck,
  UserX,
  Clock,
  AlertCircle,
  Eye,
  Trash2,
  Calendar,
  Building2,
  Mail,
  Phone,
  CheckCircle,
  XCircle
} from 'lucide-react';

export default function ConsentIndex() {
  const [consents, setConsents] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  const fetchConsents = async (page = 1) => {
    setLoading(true);
    try {
      const queryParams = new URLSearchParams({
        page: page.toString(),
        per_page: pagination.per_page.toString(),
        search: searchTerm,
        status: statusFilter,
      });

      // Remove empty values
      for (const [key, value] of [...queryParams.entries()]) {
        if (!value || value === 'all') {
          queryParams.delete(key);
        }
      }

      const response = await fetch(`/api/consent?${queryParams.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch consents');
      }

      const data = await response.json();
      setConsents(data.data || []);
      setPagination(data.meta || {});
    } catch (error) {
      console.error('Error fetching consents:', error);
      setConsents([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchConsents();
  }, [searchTerm, statusFilter]);

  const handleSearch = (value) => {
    setSearchTerm(value);
  };

  const handleStatusFilter = (value) => {
    setStatusFilter(value);
  };

  const handleRevokeConsent = async (consentId) => {
    try {
      const response = await fetch(`/api/consent/${consentId}/revoke`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to revoke consent');
      }

      // Refresh the list
      fetchConsents(pagination.current_page);
    } catch (error) {
      console.error('Error revoking consent:', error);
      alert('Failed to revoke consent. Please try again.');
    }
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const getConsentStatus = (consent) => {
    if (consent.status === 'active') {
      return {
        label: 'Active',
        variant: 'default',
        icon: <UserCheck className="h-3 w-3" />,
        color: 'text-green-600'
      };
    } else if (consent.status === 'expired') {
      return {
        label: 'Expired',
        variant: 'destructive',
        icon: <Clock className="h-3 w-3" />,
        color: 'text-orange-600'
      };
    } else if (consent.status === 'revoked') {
      return {
        label: 'Revoked',
        variant: 'secondary',
        icon: <UserX className="h-3 w-3" />,
        color: 'text-red-600'
      };
    } else {
      return {
        label: 'Pending',
        variant: 'outline',
        icon: <Clock className="h-3 w-3" />,
        color: 'text-yellow-600'
      };
    }
  };

  const mockConsents = [
    {
      id: 1,
      medical_professional: {
        id: 1,
        name: 'Dr. Sarah Johnson',
        email: 'sarah.johnson@medicenter.com',
        phone: '+1 (555) 123-4567',
        specialty: 'Cardiology',
        license_number: 'MD12345',
        clinic_name: 'Heart & Wellness Center',
        clinic_address: '123 Medical Plaza, Suite 200'
      },
      status: 'active',
      granted_at: '2024-01-15T10:30:00Z',
      expires_at: '2025-01-15T10:30:00Z',
      purpose: 'Ongoing cardiac monitoring and treatment',
      permissions: ['vital_signs', 'recommendations', 'medical_history'],
      last_accessed: '2024-09-20T14:22:00Z',
      access_count: 12
    },
    {
      id: 2,
      medical_professional: {
        id: 2,
        name: 'Dr. Michael Chen',
        email: 'm.chen@familycare.org',
        phone: '+1 (555) 987-6543',
        specialty: 'Family Medicine',
        license_number: 'MD67890',
        clinic_name: 'Family Care Medical Group',
        clinic_address: '456 Health Street, Building A'
      },
      status: 'active',
      granted_at: '2024-03-22T16:45:00Z',
      expires_at: '2025-03-22T16:45:00Z',
      purpose: 'Primary care and annual health assessments',
      permissions: ['vital_signs', 'medical_history'],
      last_accessed: '2024-09-18T09:15:00Z',
      access_count: 8
    },
    {
      id: 3,
      medical_professional: {
        id: 3,
        name: 'Dr. Emily Rodriguez',
        email: 'e.rodriguez@specialistcare.com',
        phone: '+1 (555) 456-7890',
        specialty: 'Endocrinology',
        license_number: 'MD11111',
        clinic_name: 'Diabetes & Hormone Clinic',
        clinic_address: '789 Wellness Boulevard'
      },
      status: 'expired',
      granted_at: '2023-05-10T12:00:00Z',
      expires_at: '2024-05-10T12:00:00Z',
      purpose: 'Diabetes management consultation',
      permissions: ['vital_signs', 'recommendations'],
      last_accessed: '2024-05-08T11:30:00Z',
      access_count: 5
    }
  ];

  const displayConsents = consents.length > 0 ? consents : mockConsents;
  const filteredConsents = displayConsents.filter(consent => {
    const matchesSearch = consent.medical_professional.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         consent.medical_professional.specialty.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         consent.medical_professional.clinic_name.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = statusFilter === 'all' || consent.status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  return (
    <AppLayout title="Consent Management">
      <Head title="Consent Management" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">Consent Management</h1>
            <p className="text-muted-foreground">
              Manage medical professional access to your health data
            </p>
          </div>
          <div className="flex items-center space-x-2">
            <Badge variant="secondary" className="bg-blue-100 text-blue-800">
              <Shield className="h-3 w-3 mr-1" />
              Your Data, Your Control
            </Badge>
          </div>
        </div>

        {/* Search and Filters */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Filter className="h-5 w-5" />
              Search & Filter
            </CardTitle>
            <CardDescription>
              Find and filter consent agreements by provider name, specialty, or clinic
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-medium">Search Providers</label>
                <div className="relative">
                  <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Search by name, specialty, or clinic..."
                    value={searchTerm}
                    onChange={(e) => handleSearch(e.target.value)}
                    className="pl-10"
                  />
                </div>
              </div>

              <div className="space-y-2">
                <label className="text-sm font-medium">Consent Status</label>
                <Select value={statusFilter} onValueChange={handleStatusFilter}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Consents</SelectItem>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="expired">Expired</SelectItem>
                    <SelectItem value="revoked">Revoked</SelectItem>
                    <SelectItem value="pending">Pending</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Statistics Cards */}
        <div className="grid gap-4 md:grid-cols-4">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Shield className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Total Consents</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold">{filteredConsents.length}</div>
                <div className="text-xs text-muted-foreground">
                  Medical access agreements
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <CheckCircle className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Active</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold text-green-600">
                  {filteredConsents.filter(c => c.status === 'active').length}
                </div>
                <div className="text-xs text-muted-foreground">
                  Currently valid
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Clock className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Expired</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold text-orange-600">
                  {filteredConsents.filter(c => c.status === 'expired').length}
                </div>
                <div className="text-xs text-muted-foreground">
                  Need renewal
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <XCircle className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Revoked</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold text-red-600">
                  {filteredConsents.filter(c => c.status === 'revoked').length}
                </div>
                <div className="text-xs text-muted-foreground">
                  Access removed
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Consents List */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Shield className="h-4 w-4" />
              Medical Provider Access
              {filteredConsents.length > 0 && (
                <Badge variant="secondary">
                  {filteredConsents.length} agreements
                </Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                <p className="text-muted-foreground">Loading consent agreements...</p>
              </div>
            ) : filteredConsents.length === 0 ? (
              <div className="text-center py-12">
                <Shield className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <div className="text-lg font-medium mb-2">No consent agreements found</div>
                <p className="text-muted-foreground mb-4">
                  {searchTerm || statusFilter !== 'all'
                    ? "No agreements match your current search criteria."
                    : "You haven't granted access to any medical professionals yet."
                  }
                </p>
                <Button>
                  <Plus className="h-4 w-4 mr-2" />
                  Grant New Access
                </Button>
              </div>
            ) : (
              <div className="space-y-4">
                {filteredConsents.map((consent) => {
                  const consentStatus = getConsentStatus(consent);
                  const isActive = consent.status === 'active';
                  const isExpired = consent.status === 'expired';

                  return (
                    <div
                      key={consent.id}
                      className="border rounded-lg p-6 hover:shadow-md transition-shadow"
                    >
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center gap-3 mb-3">
                            <div>
                              <h3 className="font-semibold text-lg">{consent.medical_professional.name}</h3>
                              <p className="text-sm text-muted-foreground">{consent.medical_professional.specialty}</p>
                            </div>
                            <Badge
                              variant={consentStatus.variant}
                              className="flex items-center gap-1"
                            >
                              {consentStatus.icon}
                              {consentStatus.label}
                            </Badge>
                          </div>

                          <div className="grid grid-cols-1 md:grid-cols-2 gap-6 mb-4">
                            <div className="space-y-3">
                              <div className="flex items-center gap-2">
                                <Building2 className="h-4 w-4 text-muted-foreground" />
                                <div>
                                  <div className="font-medium">{consent.medical_professional.clinic_name}</div>
                                  <div className="text-sm text-muted-foreground">
                                    {consent.medical_professional.clinic_address}
                                  </div>
                                </div>
                              </div>

                              <div className="flex items-center gap-2">
                                <Mail className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">{consent.medical_professional.email}</span>
                              </div>

                              <div className="flex items-center gap-2">
                                <Phone className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm">{consent.medical_professional.phone}</span>
                              </div>
                            </div>

                            <div className="space-y-3">
                              <div>
                                <span className="text-sm font-medium text-muted-foreground">Purpose:</span>
                                <p className="text-sm mt-1">{consent.purpose}</p>
                              </div>

                              <div>
                                <span className="text-sm font-medium text-muted-foreground">Permissions:</span>
                                <div className="flex flex-wrap gap-1 mt-1">
                                  {consent.permissions.map((permission) => (
                                    <Badge key={permission} variant="outline" className="text-xs">
                                      {permission.replace('_', ' ')}
                                    </Badge>
                                  ))}
                                </div>
                              </div>

                              <div className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                  <span className="text-muted-foreground">Granted:</span>
                                  <div>{formatDate(consent.granted_at)}</div>
                                </div>
                                <div>
                                  <span className="text-muted-foreground">Expires:</span>
                                  <div className={isExpired ? 'text-red-600' : ''}>
                                    {formatDate(consent.expires_at)}
                                  </div>
                                </div>
                                <div>
                                  <span className="text-muted-foreground">Last Access:</span>
                                  <div>{consent.last_accessed ? formatDate(consent.last_accessed) : 'Never'}</div>
                                </div>
                                <div>
                                  <span className="text-muted-foreground">Total Access:</span>
                                  <div>{consent.access_count} times</div>
                                </div>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div className="flex gap-2 ml-4">
                          <Button size="sm" variant="outline">
                            <Eye className="h-4 w-4 mr-2" />
                            View Details
                          </Button>
                          {isActive && (
                            <Button
                              size="sm"
                              variant="destructive"
                              onClick={() => handleRevokeConsent(consent.id)}
                            >
                              <Trash2 className="h-4 w-4 mr-2" />
                              Revoke
                            </Button>
                          )}
                          {isExpired && (
                            <Button size="sm" variant="default">
                              <Calendar className="h-4 w-4 mr-2" />
                              Renew
                            </Button>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}

                {/* Pagination */}
                {pagination.last_page > 1 && (
                  <div className="flex items-center justify-between pt-4">
                    <div className="text-sm text-muted-foreground">
                      Showing {((pagination.current_page - 1) * pagination.per_page) + 1} to{' '}
                      {Math.min(pagination.current_page * pagination.per_page, pagination.total)} of{' '}
                      {pagination.total} agreements
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.current_page === 1}
                        onClick={() => fetchConsents(pagination.current_page - 1)}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.current_page === pagination.last_page}
                        onClick={() => fetchConsents(pagination.current_page + 1)}
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

        {/* Quick Actions */}
        <Card className="bg-muted/50">
          <CardHeader>
            <CardTitle>Quick Actions</CardTitle>
            <CardDescription>
              Common consent management tasks
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <Button variant="outline" className="h-16 flex flex-col">
                <Plus className="h-5 w-5 mb-2" />
                Grant New Access
              </Button>
              <Button variant="outline" className="h-16 flex flex-col">
                <AlertCircle className="h-5 w-5 mb-2" />
                Review Expiring Consents
              </Button>
              <Button variant="outline" className="h-16 flex flex-col">
                <Shield className="h-5 w-5 mb-2" />
                Privacy Settings
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}