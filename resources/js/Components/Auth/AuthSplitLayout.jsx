import { Link } from "@inertiajs/react"
import { GalleryVerticalEnd } from "lucide-react"

import { cn } from "@/lib/utils"
import {
    PUBLIC_BODY_FONT,
    PUBLIC_DISPLAY_FONT,
    PUBLIC_LABEL_FONT,
} from "@/lib/publicTypography"

export function AuthBrand({ href = route("home"), className }) {
    return (
        <Link href={href} className={cn("inline-flex items-center gap-3", className)}>
            <div className="flex h-10 w-10 items-center justify-center rounded-2xl bg-slate-950 text-white shadow-lg shadow-slate-950/10">
                <GalleryVerticalEnd className="h-5 w-5" />
            </div>
            <div>
                <p
                    className="text-base font-extrabold tracking-tight text-slate-950"
                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                >
                    Vito Business OS
                </p>
                <p
                    className="text-[11px] font-bold uppercase tracking-[0.28em] text-slate-500"
                    style={{ fontFamily: PUBLIC_LABEL_FONT }}
                >
                    Vito Business OS
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
                "rounded-[2rem] p-6",
                invert
                    ? "border border-white/10 bg-white/5 text-white backdrop-blur-md"
                    : "bg-white text-slate-950 shadow-2xl shadow-slate-950/20",
                className
            )}
        >
            {eyebrow ? (
                <p
                    className={cn(
                        "text-[11px] font-bold uppercase tracking-[0.18em]",
                        invert ? "text-slate-300" : "text-slate-500"
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
                        invert ? "text-white" : "text-slate-950"
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
                        invert ? "text-slate-300" : "text-slate-600"
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
            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-white text-slate-950 shadow-lg shadow-slate-950/10">
                <Icon className="h-5 w-5" />
            </div>
            <div>
                <p
                    className="text-lg font-bold text-white"
                    style={{ fontFamily: PUBLIC_DISPLAY_FONT }}
                >
                    {title}
                </p>
                <p className="mt-2 text-sm leading-6 text-slate-300">{body}</p>
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
            className="grid min-h-[100svh] bg-white text-slate-950 xl:grid-cols-2"
            style={{ fontFamily: PUBLIC_BODY_FONT }}
        >
            <div className="flex flex-col gap-6 p-6 md:p-10">
                <div className="flex items-center justify-between gap-4">
                    <AuthBrand />
                    {topActions ? (
                        <div className="flex items-center gap-3">{topActions}</div>
                    ) : null}
                </div>

                <div className={cn("flex flex-1 items-center justify-center py-6", contentClassName)}>
                    {children}
                </div>
            </div>

            <aside className="relative hidden overflow-hidden bg-primary xl:block">
                <div className="absolute inset-0 bg-primary" />
                <div className="absolute inset-0 opacity-10 [background-image:linear-gradient(to_right,currentColor_1px,transparent_1px),linear-gradient(to_bottom,currentColor_1px,transparent_1px)] [background-size:72px_72px]" />

                <div className="relative flex h-full flex-col justify-between p-10 xl:p-14">
                    {aside}
                </div>
            </aside>
        </div>
    )
}
