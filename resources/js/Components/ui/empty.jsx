import * as React from "react"
import { ArrowRight, Inbox } from "lucide-react"

import { Button } from "@/Components/ui/button"
import { cn } from "@/lib/utils"

const Empty = React.forwardRef(({ className, ...props }, ref) => (
    <section
        ref={ref}
        className={cn("flex min-h-56 flex-col items-center justify-center rounded-lg border border-dashed bg-card px-6 py-10 text-center text-card-foreground", className)}
        {...props}
    />
))
Empty.displayName = "Empty"

const EmptyHeader = React.forwardRef(({ className, ...props }, ref) => (
    <div ref={ref} className={cn("flex flex-col items-center gap-3", className)} {...props} />
))
EmptyHeader.displayName = "EmptyHeader"

const EmptyIcon = ({ icon: Icon = Inbox, className, ...props }) => (
    <span
        className={cn("inline-flex size-12 items-center justify-center rounded-lg bg-muted text-muted-foreground", className)}
        {...props}
    >
        <Icon className="size-5" aria-hidden="true" />
    </span>
)
EmptyIcon.displayName = "EmptyIcon"

const EmptyTitle = React.forwardRef(({ className, ...props }, ref) => (
    <h3 ref={ref} className={cn("max-w-md text-pretty text-lg font-semibold tracking-tight", className)} {...props} />
))
EmptyTitle.displayName = "EmptyTitle"

const EmptyDescription = React.forwardRef(({ className, ...props }, ref) => (
    <p ref={ref} className={cn("max-w-xl text-pretty text-sm leading-6 text-muted-foreground", className)} {...props} />
))
EmptyDescription.displayName = "EmptyDescription"

const EmptyContent = React.forwardRef(({ className, ...props }, ref) => (
    <div ref={ref} className={cn("mt-6 flex flex-col items-center justify-center gap-3 sm:flex-row", className)} {...props} />
))
EmptyContent.displayName = "EmptyContent"

function EmptyAction({ children, icon: Icon = ArrowRight, ...props }) {
    return (
        <Button {...props}>
            {children}
            {Icon ? <Icon data-icon="inline-end" aria-hidden="true" /> : null}
        </Button>
    )
}
EmptyAction.displayName = "EmptyAction"

export {
    Empty,
    EmptyAction,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyIcon,
    EmptyTitle,
}
