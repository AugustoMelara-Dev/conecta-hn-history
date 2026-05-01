import { Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';
import { cn } from '@/lib/utils';

export default function AppPageHeader({
    title,
    description,
    backHref,
    backLabel = 'Volver',
    actions,
    meta,
    className = '',
    children,
}) {
    return (
        <header className={cn('sticky top-0 z-40 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80', className)}>
            <div className="mx-auto flex min-h-16 max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                <div className="flex min-w-0 items-center gap-3">
                    {backHref ? (
                        <TooltipProvider>
                            <Tooltip>
                                <TooltipTrigger asChild>
                                    <Button asChild variant="ghost" size="icon" aria-label={backLabel}>
                                        <Link href={backHref}>
                                            <ArrowLeft aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>{backLabel}</TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    ) : null}

                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold text-foreground">{title}</h1>
                        {description ? (
                            <p className="mt-1 text-sm text-muted-foreground">{description}</p>
                        ) : null}
                    </div>
                </div>

                <div className="flex shrink-0 items-center gap-3">
                    {meta}
                    {actions}
                </div>
            </div>
            {children}
        </header>
    );
}
