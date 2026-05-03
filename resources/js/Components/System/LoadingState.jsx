import { Skeleton } from "@/Components/ui/skeleton";
import { Spinner } from "@/Components/ui/spinner";
import { cn } from "@/lib/utils";

export default function LoadingState({ lines = 3, title, className, variant = "skeleton" }) {
    if (variant === "inline") {
        return (
            <div className={cn("inline-flex items-center gap-2 text-sm text-muted-foreground", className)} aria-live="polite" aria-busy="true" role="status">
                <Spinner className="h-4 w-4" />
                <span>{title || "Cargando..."}</span>
            </div>
        );
    }

    return (
        <div className={cn("space-y-3", className)} aria-live="polite" aria-busy="true" role="status">
            {Array.from({ length: lines }).map((_, index) => (
                <Skeleton key={index} className="h-8 w-full" />
            ))}
        </div>
    );
}
