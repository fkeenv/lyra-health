import { Head, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

export default function Dashboard({
    summary = {},
    recentVitalSigns = [],
    flaggedRecords = [],
    recommendations = []
}) {
    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Health Dashboard</h1>
                        <p className="text-muted-foreground">
                            Track your vital signs and monitor your health progress
                        </p>
                    </div>
                    <Link href="/vital-signs/create">
                        <Button>Record Vital Signs</Button>
                    </Link>
                </div>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Total Records
                            </CardTitle>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                className="h-4 w-4 text-muted-foreground"
                            >
                                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                                <circle cx="9" cy="7" r="4" />
                                <path d="m22 21-3-3 3-3" />
                            </svg>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{summary.total_records || 0}</div>
                            <p className="text-xs text-muted-foreground">
                                Last 30 days
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Flagged Records
                            </CardTitle>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                className="h-4 w-4 text-muted-foreground"
                            >
                                <path d="M22 12h-4l-3 9L9 3l-3 9H2" />
                            </svg>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {summary.flagged_records || 0}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Needs attention
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Active Recommendations
                            </CardTitle>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                className="h-4 w-4 text-muted-foreground"
                            >
                                <path d="M12 2v20m8-10H4" />
                            </svg>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{recommendations.length || 0}</div>
                            <p className="text-xs text-muted-foreground">
                                Unread suggestions
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">
                                Recording Streak
                            </CardTitle>
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth="2"
                                className="h-4 w-4 text-muted-foreground"
                            >
                                <path d="M8 2v4" />
                                <path d="M16 2v4" />
                                <rect width="18" height="18" x="3" y="4" rx="2" />
                                <path d="M3 10h18" />
                            </svg>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">7</div>
                            <p className="text-xs text-muted-foreground">
                                Days in a row
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {/* Recent Vital Signs */}
                    <Card className="col-span-1 lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Recent Vital Signs</CardTitle>
                            <CardDescription>
                                Your latest health measurements
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recentVitalSigns.length > 0 ? (
                                <div className="space-y-4">
                                    {recentVitalSigns.slice(0, 5).map((record, index) => (
                                        <div key={index} className="flex items-center justify-between p-4 rounded-lg border">
                                            <div>
                                                <p className="font-medium">
                                                    {record.vital_sign_type?.display_name || 'Unknown Type'}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {new Date(record.measured_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-semibold">
                                                    {record.value_primary}
                                                    {record.value_secondary && `/${record.value_secondary}`}
                                                    <span className="text-sm text-muted-foreground ml-1">
                                                        {record.unit}
                                                    </span>
                                                </p>
                                                {record.is_flagged && (
                                                    <span className="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-orange-100 text-orange-800">
                                                        Flagged
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                    <div className="pt-4">
                                        <Link href="/vital-signs">
                                            <Button variant="outline" className="w-full">
                                                View All Records
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <p className="text-muted-foreground mb-4">
                                        No vital signs recorded yet
                                    </p>
                                    <Link href="/vital-signs/create">
                                        <Button>Record Your First Measurement</Button>
                                    </Link>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Recommendations */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Health Recommendations</CardTitle>
                            <CardDescription>
                                Personalized health advice
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {recommendations.length > 0 ? (
                                <div className="space-y-4">
                                    {recommendations.slice(0, 3).map((recommendation, index) => (
                                        <div key={index} className="p-4 rounded-lg border">
                                            <div className="flex items-start justify-between">
                                                <div className="flex-1">
                                                    <p className="font-medium text-sm">
                                                        {recommendation.title}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {recommendation.message}
                                                    </p>
                                                </div>
                                                <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${
                                                    recommendation.severity === 'high' ? 'bg-red-100 text-red-800' :
                                                    recommendation.severity === 'medium' ? 'bg-yellow-100 text-yellow-800' :
                                                    'bg-blue-100 text-blue-800'
                                                }`}>
                                                    {recommendation.recommendation_type}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                    <div className="pt-2">
                                        <Link href="/recommendations">
                                            <Button variant="outline" className="w-full">
                                                View All Recommendations
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="text-center py-8">
                                    <p className="text-muted-foreground text-sm">
                                        No recommendations available
                                    </p>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Quick Actions</CardTitle>
                        <CardDescription>
                            Common tasks and navigation
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                            <Link href="/vital-signs/create">
                                <Button variant="outline" className="w-full h-20 flex flex-col">
                                    <svg className="h-6 w-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                    </svg>
                                    Record Vital Signs
                                </Button>
                            </Link>

                            <Link href="/vital-signs/trends">
                                <Button variant="outline" className="w-full h-20 flex flex-col">
                                    <svg className="h-6 w-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M7 12l3-3 3 3 4-4" />
                                    </svg>
                                    View Trends
                                </Button>
                            </Link>

                            <Link href="/recommendations">
                                <Button variant="outline" className="w-full h-20 flex flex-col">
                                    <svg className="h-6 w-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                                    </svg>
                                    Health Tips
                                </Button>
                            </Link>

                            <Link href="/consent">
                                <Button variant="outline" className="w-full h-20 flex flex-col">
                                    <svg className="h-6 w-6 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.031 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Privacy Settings
                                </Button>
                            </Link>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}