import AppLayout from '@/Layouts/AppLayout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';

export default function Welcome() {
    return (
        <AppLayout title="Welcome">
            <Card>
                <CardHeader>
                    <CardTitle>Welcome to Vital Signs Tracker</CardTitle>
                    <CardDescription>
                        Track your health metrics and monitor your progress over time.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex gap-4">
                        <Button>Get Started</Button>
                        <Button variant="outline">Learn More</Button>
                    </div>
                </CardContent>
            </Card>
        </AppLayout>
    );
}