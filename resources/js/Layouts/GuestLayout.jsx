import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-[100svh] flex-col items-center bg-muted/50 px-4 pt-6 text-foreground sm:justify-center sm:pt-0">
            <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-background focus:px-4 focus:py-2 focus:text-sm focus:font-semibold focus:shadow-md">
                Saltar al contenido
            </a>
            <div>
                <Link href="/">
                    <ApplicationLogo className="h-16 w-16 fill-current text-foreground" />
                </Link>
            </div>

            <main id="main-content" tabIndex={-1} className="mt-6 w-full max-w-md rounded-xl border bg-card px-6 py-6 text-card-foreground shadow-md outline-none">
                {children}
            </main>
        </div>
    );
}
