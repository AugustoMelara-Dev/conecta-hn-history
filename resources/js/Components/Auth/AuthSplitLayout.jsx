import { Link } from "@inertiajs/react"
import DovraMonogram from "@/Components/Brand/DovraMonogram"

import { SkipLink } from "@/Components/ui/skip-link"
import { cn } from "@/lib/utils"
import {
    PUBLIC_BODY_FONT,
    PUBLIC_DISPLAY_FONT,
    PUBLIC_LABEL_FONT,
} from "@/lib/publicTypography"

export function AuthBrand({ href = "/", className }) {
    return (
        <Link href={href} className={cn("inline-flex items-center gap-3", className)}>
            <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-md">
                <DovraMonogram variant="mark" />
            </div>
            <div>
                <p
                    className="text-base font-extrabold tracking-tight text-foreground"
                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                >
                    Dovra AI
                </p>
                <p
                    className="text-[11px] font-bold uppercase tracking-[0.28em] text-muted-foreground"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    Dovra AI
                </p>
            </div>
        </Link>
    )
}

export function AuthAsideCard({
    className,
    eyebrow,
    title,
    description,
    invert = false,
    children,
}) {
    return (
        <div
            className={cn(
                "rounded-lg p-5",
                invert
                    ? "border border-primary-foreground/10 bg-primary-foreground/5 text-primary-foreground backdrop-blur-md"
                    : "border bg-card text-card-foreground shadow-md",
                className
            )}
        >
            {eyebrow ? (
                <p
                    className={cn(
                        "text-[11px] font-bold uppercase tracking-[0.18em]",
                        invert ? "text-primary-foreground/70" : "text-muted-foreground"
                    )}
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    {eyebrow}
                </p>
            ) : null}
            {title ? (
                <p
                    className={cn(
                        "mt-3 text-2xl font-extrabold tracking-tight",
                        invert ? "text-primary-foreground" : "text-foreground"
                    )}
                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                >
                    {title}
                </p>
            ) : null}
            {description ? (
                <p
                    className={cn(
                        "mt-2 text-sm leading-6",
                        invert ? "text-primary-foreground/70" : "text-muted-foreground"
                    )}
                >
                    {description}
                </p>
            ) : null}
            {children}
        </div>
    )
}

export function AuthAsideFeature({ icon: Icon, title, body }) {
    return (
        <div className="flex items-start gap-4">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-foreground text-primary shadow-md">
                <Icon className="h-5 w-5" />
            </div>
            <div>
                <p
                    className="text-lg font-bold text-primary-foreground"
                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                >
                    {title}
                </p>
                <p className="mt-2 text-sm leading-6 text-primary-foreground/70">{body}</p>
            </div>
        </div>
    )
}

export default function AuthSplitLayout({
    children,
    topActions = null,
    aside = null,
    contentClassName,
}) {
    return (
        <div
            className="grid min-h-[100svh] min-w-0 bg-[radial-gradient(circle_at_top_right,hsl(var(--secondary))_0,transparent_34%),linear-gradient(180deg,hsl(var(--background))_0%,hsl(var(--muted))_100%)] text-foreground xl:grid-cols-[0.9fr_1.1fr]"
            style={{ fontFamily: PUBLIC_BODY_FONT }}
        >
            <SkipLink />
            <div className="flex min-w-0 flex-col gap-6 p-6 md:p-10">
                <header className="flex min-w-0 flex-wrap items-center justify-between gap-4">
                    <AuthBrand />
                    {topActions ? (
                        <div className="flex min-w-0 flex-wrap items-center justify-end gap-3">{topActions}</div>
                    ) : null}
                </header>

                <main id="main-content" tabIndex={-1} className={cn("flex min-w-0 flex-1 items-center justify-center py-6 outline-none", contentClassName)}>
                    {children}
                </main>
            </div>

            <aside className="relative hidden overflow-hidden bg-primary text-primary-foreground xl:block">
                <div className="absolute inset-0 bg-primary" />
                <div className="absolute inset-0 opacity-10 [background-image:linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] [background-size:72px_72px]" />

                <div className="relative flex h-full flex-col justify-between p-10 xl:p-14">
                    {aside}
                </div>
            </aside>
        </div>
    )
}
