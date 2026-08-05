import * as React from "react"

import { cn } from "@/lib/utils"

const variants = {
    default: "border-border bg-background text-foreground",
    success: "border-success/30 bg-success/10 text-success",
    destructive: "border-destructive/30 bg-destructive/10 text-destructive",
    warning: "border-warning/30 bg-warning/10 text-warning",
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
