import * as React from "react"

import { cn } from "@/lib/utils"

const variants = {
    default: "border-slate-200 bg-white text-slate-900",
    success: "border-emerald-200 bg-emerald-50 text-emerald-950",
    destructive: "border-rose-200 bg-rose-50 text-rose-950",
    warning: "border-amber-200 bg-amber-50 text-amber-950",
}

const Alert = React.forwardRef(({ className, variant = "default", ...props }, ref) => (
    <div
        ref={ref}
        role="status"
        className={cn(
            "rounded-lg border px-4 py-3 text-sm",
            variants[variant] ?? variants.default,
            className
        )}
        {...props}
    />
))
Alert.displayName = "Alert"

const AlertTitle = React.forwardRef(({ className, ...props }, ref) => (
    <h5 ref={ref} className={cn("mb-1 font-semibold leading-none", className)} {...props} />
))
AlertTitle.displayName = "AlertTitle"

const AlertDescription = React.forwardRef(({ className, ...props }, ref) => (
    <div ref={ref} className={cn("text-sm leading-5", className)} {...props} />
))
AlertDescription.displayName = "AlertDescription"

export { Alert, AlertDescription, AlertTitle }
