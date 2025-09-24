import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Activity, Heart, TrendingUp, Shield, Plus } from 'lucide-react';

export default function AppLayout({ title, children }) {
    const navigation = [
        { name: 'Dashboard', href: '/dashboard', icon: Activity },
        { name: 'Record', href: '/vital-signs/create', icon: Plus },
        { name: 'Trends', href: '/vital-signs/trends', icon: TrendingUp },
        { name: 'Health Tips', href: '/recommendations', icon: Heart },
        { name: 'Privacy', href: '/consent', icon: Shield },
    ];

    const isActive = (href) => {
        return window.location.pathname === href;
    };

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen bg-background">
                <nav className="border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex justify-between h-16">
                            <div className="flex items-center space-x-8">
                                <Link href="/dashboard" className="flex items-center space-x-2">
                                    <Activity className="h-6 w-6 text-primary" />
                                    <h1 className="text-xl font-bold">Lyra Health</h1>
                                </Link>

                                <div className="hidden md:flex space-x-1">
                                    {navigation.map((item) => {
                                        const IconComponent = item.icon;
                                        return (
                                            <Link
                                                key={item.name}
                                                href={item.href}
                                                className={`inline-flex items-center px-3 py-2 rounded-md text-sm font-medium transition-colors ${
                                                    isActive(item.href)
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'text-muted-foreground hover:text-foreground hover:bg-accent'
                                                }`}
                                            >
                                                <IconComponent className="h-4 w-4 mr-2" />
                                                {item.name}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>

                            <div className="flex items-center space-x-4">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.post('/logout')}
                                >
                                    Sign Out
                                </Button>
                            </div>
                        </div>
                    </div>
                </nav>

                {/* Mobile Navigation */}
                <div className="md:hidden border-b bg-background">
                    <div className="px-4 py-2">
                        <div className="flex space-x-1 overflow-x-auto">
                            {navigation.map((item) => {
                                const IconComponent = item.icon;
                                return (
                                    <Link
                                        key={item.name}
                                        href={item.href}
                                        className={`flex flex-col items-center px-3 py-2 rounded-md text-xs font-medium min-w-[60px] transition-colors ${
                                            isActive(item.href)
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground hover:text-foreground hover:bg-accent'
                                        }`}
                                    >
                                        <IconComponent className="h-4 w-4 mb-1" />
                                        {item.name}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </div>

                <main className="flex-1">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                        {children}
                    </div>
                </main>
            </div>
        </>
    );
}