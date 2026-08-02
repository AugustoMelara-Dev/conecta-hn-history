import { cn } from "@/lib/utils"

export function SkipLink({ targetId = "main-content", className, children = "Saltar al contenido principal" }) {
    return (
        <a
            href={`#${targetId}`}
            className={cn(
                "fixed left-4 top-4 z-[100] -translate-y-24 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg transition-transform focus:translate-y-0 focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 motion-reduce:transition-none",
                className,
            )}
        >
            {children}
        </a>
    )
}