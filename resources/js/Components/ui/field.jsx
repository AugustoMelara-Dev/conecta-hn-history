import * as React from "react"

import { Label } from "@/Components/ui/label"
import { cn } from "@/lib/utils"

const FieldGroup = React.forwardRef(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn("flex flex-col gap-6", className)}
        {...props}
    />
))
FieldGroup.displayName = "FieldGroup"

const Field = React.forwardRef(({ className, ...props }, ref) => (
    <div
        ref={ref}
        className={cn("grid gap-2", className)}
        {...props}
    />
))
Field.displayName = "Field"

const FieldLabel = React.forwardRef(({ className, ...props }, ref) => (
    <Label
        ref={ref}
        className={cn(
            "text-sm font-medium leading-none text-slate-900 peer-disabled:cursor-not-allowed peer-disabled:opacity-70",
            className
        )}
        {...props}
    />
))
FieldLabel.displayName = "FieldLabel"

const FieldDescription = React.forwardRef(({ className, ...props }, ref) => (
    <p
        ref={ref}
        className={cn("text-sm text-slate-500", className)}
        {...props}
    />
))
FieldDescription.displayName = "FieldDescription"

const FieldSeparator = React.forwardRef(({ className, children, ...props }, ref) => (
    <div
        ref={ref}
        className={cn("flex items-center gap-4 text-sm text-slate-500", className)}
        {...props}
    >
        <div className="h-px flex-1 bg-slate-200" />
        {children ? (
            <span className="text-sm">
                {children}
            </span>
        ) : null}
        <div className="h-px flex-1 bg-slate-200" />
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
