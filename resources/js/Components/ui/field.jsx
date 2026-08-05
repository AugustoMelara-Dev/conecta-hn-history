import * as React from "react"

import { Label } from "@/Components/ui/label"
import { cn } from "@/lib/utils"

const FieldGroup = React.forwardRef(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn("flex min-w-0 flex-col gap-6", className)}
        {...props}
    />
))
FieldGroup.displayName = "FieldGroup"

const Field = React.forwardRef(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn("grid min-w-0 grid-cols-[minmax(0,1fr)] gap-2", className)}
        {...props}
    />
))
Field.displayName = "Field"

const FieldLabel = React.forwardRef(({ className, ...props }, ref) => (
    <Label
        ref={ref}
        className={cn(
            "min-w-0 break-words text-sm font-medium leading-none text-foreground peer-disabled:cursor-not-allowed peer-disabled:opacity-70",
            className
        )}
        {...props}
    />
))
FieldLabel.displayName = "FieldLabel"

const FieldDescription = React.forwardRef(({ className, ...props }, ref) => (
    <p
        ref={ref}
        className={cn("min-w-0 break-words text-sm text-muted-foreground", className)}
        {...props}
    />
))
FieldDescription.displayName = "FieldDescription"

const FieldSeparator = React.forwardRef(({ className, children, ...props }, ref) => (
    <div
        ref={ref}
        className={cn("flex items-center gap-4 text-sm text-muted-foreground", className)}
        {...props}
    >
        <div className="h-px flex-1 bg-border" />
        {children ? (
            <span className="text-sm">
                {children}
            </span>
        ) : null}
        <div className="h-px flex-1 bg-border" />
    </div>
))
FieldSeparator.displayName = "FieldSeparator"

export {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldSeparator,
}
