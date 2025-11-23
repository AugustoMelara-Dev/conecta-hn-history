import { Link } from '@inertiajs/react';
import { Search, Bell, Menu } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import useScrollDirection from '@/Hooks/useScrollDirection';
import { cn } from '@/lib/utils';

export default function SmartNavbar() {
    const scrollDirection = useScrollDirection(10);

    return (
        <nav
            className={cn(
                "sticky top-0 z-nav w-full border-b border-border/40 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 transition-transform duration-300",
                scrollDirection === 'down' ? '-translate-y-full' : 'translate-y-0'
            )}
        >
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="flex h-16 items-center justify-between gap-4">
                    {/* Logo */}
                    <Link href="/" className="flex items-center gap-2 shrink-0">
                        <div className="w-8 h-8 bg-primary rounded-lg flex items-center justify-center">
                            <span className="text-primary-foreground font-bold text-sm">C</span>
                        </div>
                        <span className="font-bold text-lg tracking-tight hidden sm:inline">
                            Conecta HN
                        </span>
                    </Link>

                    {/* Search Bar - Hidden on mobile */}
                    <div className="hidden md:flex flex-1 max-w-xl">
                        <div className="relative w-full">
                            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder="Buscar negocios, productos..."
                                className="pl-10 w-full"
                            />
                        </div>
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-2">
                        <Button variant="ghost" size="icon" className="relative">
                            <Bell className="h-5 w-5" />
                            <span className="absolute top-1 right-1 w-2 h-2 bg-destructive rounded-full"></span>
                        </Button>

                        <Button variant="ghost" size="icon" className="md:hidden">
                            <Menu className="h-5 w-5" />
                        </Button>

                        <Link href="/mi-cuenta" className="hidden md:block">
                            <Button variant="ghost" className="gap-2">
                                <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                    <span className="text-primary text-sm font-semibold">U</span>
                                </div>
                            </Button>
                        </Link>
                    </div>
                </div>
            </div>
        </nav>
    );
}
