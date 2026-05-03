import { Badge } from "@/Components/ui/badge";
import { Button } from "@/Components/ui/button";
import { cn } from "@/lib/utils";
import { Link } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { isValidElement } from "react";

export default function PageHeader({
    title,
    subtitle,
    backHref,
    backLabel = "Volver",
    badge,
    className,
    actions,
    sticky = true,
}) {
    return (
        <header className={cn(sticky && "sticky top-0 z-40", "border-b bg-background/95 backdrop-blur", className)}>
            <div className="mx-auto flex h-16 w-full max-w-6xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div className="flex items-center gap-4">
                    {backHref ? (
                        <Button asChild variant="ghost" size="icon" className="h-9 w-9 shrink-0" aria-label={backLabel}>
                            <Link href={backHref}>
                                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                            </Link>
                        </Button>
                    ) : null}
                    <div>
                        <h1 className="text-xl font-semibold text-foreground">{title}</h1>
                        {subtitle ? <p className="text-xs text-muted-foreground">{subtitle}</p> : null}
                    </div>
                </div>
                <div className="flex items-center gap-3">
                    {badge ? (isValidElement(badge) ? badge : <Badge variant="secondary">{badge}</Badge>) : null}
                    {actions}
                </div>
            </div>
        </header>
    );
}
