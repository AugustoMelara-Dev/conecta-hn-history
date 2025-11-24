import { useEffect } from 'react';
import SmartNavbar from '@/Components/Organisms/SmartNavbar';
import MobileBottomNav from '@/Components/Organisms/MobileBottomNav';
import GlobalLoginModal from '@/Components/Organisms/Auth/GlobalLoginModal';
import { Toaster } from '@/Components/ui/sonner';

export default function AppLayout({ children }) {
    // Prevent overscroll on iOS for native feel
    useEffect(() => {
        document.body.style.overscrollBehavior = 'none';
        return () => {
            document.body.style.overscrollBehavior = 'auto';
        };
    }, []);

    return (
        <div className="flex flex-col h-[100dvh] overflow-hidden bg-background text-foreground antialiased selection:bg-primary/20">
            {/* Level 2 (z-50): Fixed Header - Always visible on Desktop/Tablet */}
            <header className="hidden md:block fixed top-0 left-0 right-0 z-50 w-full">
                <SmartNavbar />
            </header>

            {/* Level 1 (z-base): Main Content Area with Independent Scroll */}
            <main className="flex-1 overflow-y-auto overflow-x-hidden scroll-smooth relative w-full pt-20 md:pt-24">
                {/* Content container with responsive padding */}
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 pb-20 md:pb-6">
                    {children}
                </div>
            </main>

            {/* Level 2 (z-40): Bottom Navigation - Mobile Only */}
            <nav className="md:hidden flex-none z-nav bg-background/80 border-t border-border/40 backdrop-blur-lg">
                <MobileBottomNav />
            </nav>
        </div>
    );
}
