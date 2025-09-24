import { useState, useEffect } from 'react';
import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Input } from '@/Components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import {
  Users,
  Search,
  Filter,
  Eye,
  Calendar,
  Heart,
  Activity,
  Clock,
  UserCheck,
  Shield,
  AlertCircle
} from 'lucide-react';

export default function MedicalPatients() {
  const [patients, setPatients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0,
  });

  const fetchPatients = async (page = 1) => {
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

      const response = await fetch(`/api/medical/patients?${queryParams.toString()}`, {
        headers: {
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
        },
      });

      if (!response.ok) {
        throw new Error('Failed to fetch patients');
      }

      const data = await response.json();
      setPatients(data.data || []);
      setPagination(data.meta || {});
    } catch (error) {
      console.error('Error fetching patients:', error);
      setPatients([]);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchPatients();
  }, [searchTerm, statusFilter]);

  const handleSearch = (value) => {
    setSearchTerm(value);
  };

  const handleStatusFilter = (value) => {
    setStatusFilter(value);
  };

  const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric',
    });
  };

  const getConsentStatus = (patient) => {
    if (patient.consent_status === 'active') {
      return {
        label: 'Active',
        variant: 'default',
        icon: <UserCheck className="h-3 w-3" />
      };
    } else if (patient.consent_status === 'expired') {
      return {
        label: 'Expired',
        variant: 'destructive',
        icon: <Clock className="h-3 w-3" />
      };
    } else {
      return {
        label: 'No Consent',
        variant: 'secondary',
        icon: <AlertCircle className="h-3 w-3" />
      };
    }
  };

  const mockPatients = [
    {
      id: 1,
      name: 'John Doe',
      email: 'john.doe@example.com',
      date_of_birth: '1985-03-15',
      consent_status: 'active',
      consent_granted_at: '2024-01-15T10:30:00Z',
      consent_expires_at: '2025-01-15T10:30:00Z',
      last_vital_signs: '2024-09-20T08:15:00Z',
      total_records: 45,
      flagged_records: 2,
    },
    {
      id: 2,
      name: 'Jane Smith',
      email: 'jane.smith@example.com',
      date_of_birth: '1990-07-22',
      consent_status: 'active',
      consent_granted_at: '2024-02-01T14:20:00Z',
      consent_expires_at: '2025-02-01T14:20:00Z',
      last_vital_signs: '2024-09-22T19:45:00Z',
      total_records: 32,
      flagged_records: 0,
    },
    {
      id: 3,
      name: 'Robert Johnson',
      email: 'robert.j@example.com',
      date_of_birth: '1978-11-08',
      consent_status: 'expired',
      consent_granted_at: '2023-06-15T09:00:00Z',
      consent_expires_at: '2024-06-15T09:00:00Z',
      last_vital_signs: '2024-06-10T12:30:00Z',
      total_records: 78,
      flagged_records: 5,
    },
  ];

  const displayPatients = patients.length > 0 ? patients : mockPatients;
  const filteredPatients = displayPatients.filter(patient => {
    const matchesSearch = patient.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                         patient.email.toLowerCase().includes(searchTerm.toLowerCase());
    const matchesStatus = statusFilter === 'all' || patient.consent_status === statusFilter;
    return matchesSearch && matchesStatus;
  });

  return (
    <AppLayout title="My Patients">
      <Head title="My Patients" />

      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold tracking-tight">My Patients</h1>
            <p className="text-muted-foreground">
              Manage patients who have granted you access to their health data
            </p>
          </div>
          <div className="flex items-center space-x-2">
            <Badge variant="secondary" className="bg-primary text-primary-foreground">
              <Shield className="h-3 w-3 mr-1" />
              Medical Professional
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
              Find and filter patients by name, email, or consent status
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <label className="text-sm font-medium">Search Patients</label>
                <div className="relative">
                  <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                  <Input
                    placeholder="Search by name or email..."
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
                    <SelectItem value="all">All Patients</SelectItem>
                    <SelectItem value="active">Active Consent</SelectItem>
                    <SelectItem value="expired">Expired Consent</SelectItem>
                    <SelectItem value="none">No Consent</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Statistics Cards */}
        <div className="grid gap-4 md:grid-cols-3">
          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <Users className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Total Patients</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold">{filteredPatients.length}</div>
                <div className="text-xs text-muted-foreground">
                  With consent access
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <UserCheck className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Active Consent</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold text-green-600">
                  {filteredPatients.filter(p => p.consent_status === 'active').length}
                </div>
                <div className="text-xs text-muted-foreground">
                  Currently accessible
                </div>
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <div className="flex items-center space-x-2">
                <AlertCircle className="h-4 w-4 text-muted-foreground" />
                <span className="text-sm font-medium text-muted-foreground">Flagged Records</span>
              </div>
              <div className="mt-2">
                <div className="text-2xl font-bold text-orange-600">
                  {filteredPatients.reduce((sum, p) => sum + (p.flagged_records || 0), 0)}
                </div>
                <div className="text-xs text-muted-foreground">
                  Requiring attention
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Patients List */}
        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Users className="h-4 w-4" />
              Patient List
              {filteredPatients.length > 0 && (
                <Badge variant="secondary">
                  {filteredPatients.length} patients
                </Badge>
              )}
            </CardTitle>
          </CardHeader>
          <CardContent>
            {loading ? (
              <div className="text-center py-8">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary mx-auto mb-4"></div>
                <p className="text-muted-foreground">Loading patients...</p>
              </div>
            ) : filteredPatients.length === 0 ? (
              <div className="text-center py-12">
                <Users className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                <div className="text-lg font-medium mb-2">No patients found</div>
                <p className="text-muted-foreground mb-4">
                  {searchTerm || statusFilter !== 'all'
                    ? "No patients match your current search criteria."
                    : "You don't have any patients with active consent yet."
                  }
                </p>
              </div>
            ) : (
              <div className="space-y-4">
                {filteredPatients.map((patient) => {
                  const consentStatus = getConsentStatus(patient);
                  const age = patient.date_of_birth ?
                    new Date().getFullYear() - new Date(patient.date_of_birth).getFullYear() :
                    null;

                  return (
                    <div
                      key={patient.id}
                      className="border rounded-lg p-6 hover:shadow-md transition-shadow"
                    >
                      <div className="flex items-start justify-between">
                        <div className="flex-1">
                          <div className="flex items-center gap-3 mb-3">
                            <div>
                              <h3 className="font-semibold text-lg">{patient.name}</h3>
                              <p className="text-sm text-muted-foreground">{patient.email}</p>
                            </div>
                            <Badge
                              variant={consentStatus.variant}
                              className="flex items-center gap-1"
                            >
                              {consentStatus.icon}
                              {consentStatus.label}
                            </Badge>
                            {patient.flagged_records > 0 && (
                              <Badge variant="destructive" className="text-xs">
                                <AlertCircle className="h-3 w-3 mr-1" />
                                {patient.flagged_records} flagged
                              </Badge>
                            )}
                          </div>

                          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
                            <div>
                              <span className="text-muted-foreground">Age:</span>
                              <span className="ml-2 font-medium">{age ? `${age} years` : 'N/A'}</span>
                            </div>
                            <div>
                              <span className="text-muted-foreground">Total Records:</span>
                              <span className="ml-2 font-medium">{patient.total_records || 0}</span>
                            </div>
                            <div>
                              <span className="text-muted-foreground">Last Reading:</span>
                              <span className="ml-2">
                                {patient.last_vital_signs ? formatDate(patient.last_vital_signs) : 'Never'}
                              </span>
                            </div>
                            <div>
                              <span className="text-muted-foreground">Consent Expires:</span>
                              <span className="ml-2">
                                {patient.consent_expires_at ? formatDate(patient.consent_expires_at) : 'N/A'}
                              </span>
                            </div>
                          </div>
                        </div>

                        <div className="flex gap-2 ml-4">
                          {patient.consent_status === 'active' && (
                            <Button size="sm" variant="outline">
                              <Eye className="h-4 w-4 mr-2" />
                              View Data
                            </Button>
                          )}
                          <Button size="sm" variant="outline">
                            <Heart className="h-4 w-4 mr-2" />
                            History
                          </Button>
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
                      {pagination.total} patients
                    </div>
                    <div className="flex gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.current_page === 1}
                        onClick={() => fetchPatients(pagination.current_page - 1)}
                      >
                        Previous
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        disabled={pagination.current_page === pagination.last_page}
                        onClick={() => fetchPatients(pagination.current_page + 1)}
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
              Common tasks for managing patient care
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="grid gap-4 md:grid-cols-3">
              <Button variant="outline" className="h-16 flex flex-col">
                <Activity className="h-5 w-5 mb-2" />
                Review Flagged Records
              </Button>
              <Button variant="outline" className="h-16 flex flex-col">
                <Calendar className="h-5 w-5 mb-2" />
                Schedule Follow-ups
              </Button>
              <Link href="/consent">
                <Button variant="outline" className="w-full h-16 flex flex-col">
                  <Shield className="h-5 w-5 mb-2" />
                  Manage Consent
                </Button>
              </Link>
            </div>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  );
}