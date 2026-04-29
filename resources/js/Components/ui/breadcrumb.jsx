import * as React from "react"
import { ChevronRight, MoreHorizontal } from "lucide-react"

import { cn } from "@/lib/utils"

const Breadcrumb = React.forwardRef(({ className, ...props }, ref) => (
    <nav ref={ref} aria-label="breadcrumb" className={cn("text-sm", className)} {...props} />
))
Breadcrumb.displayName = "Breadcrumb"

const BreadcrumbList = React.forwardRef(({ className, ...props }, ref) => (
    <ol
        ref={ref}
        className={cn("flex flex-wrap items-center gap-1.5 break-words text-muted-foreground", className)}
        {...props}
    />
))
BreadcrumbList.displayName = "BreadcrumbList"

const BreadcrumbItem = React.forwardRef(({ className, ...props }, ref) => (
    <li ref={ref} className={cn("inline-flex items-center gap-1.5", className)} {...props} />
))
BreadcrumbItem.displayName = "BreadcrumbItem"

const BreadcrumbLink = React.forwardRef(({ className, asChild, ...props }, ref) => {
    const Comp = asChild ? React.Fragment : "a"

    if (asChild) {
        return props.children
    }

    return (
        <Comp
            ref={ref}
            className={cn("font-medium transition-colors hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2", className)}
            {...props}
        />
    )
})
BreadcrumbLink.displayName = "BreadcrumbLink"

const BreadcrumbPage = React.forwardRef(({ className, ...props }, ref) => (
    <span
        ref={ref}
        role="link"
        aria-disabled="true"
        aria-current="page"
        className={cn("font-semibold text-foreground", className)}
        {...props}
    />
))
BreadcrumbPage.displayName = "BreadcrumbPage"

const BreadcrumbSeparator = ({ children, className, ...props }) => (
    <li role="presentation" aria-hidden="true" className={cn("[&>svg]:size-3.5", className)} {...props}>
        {children ?? <ChevronRight aria-hidden="true" />}
    </li>
)
BreadcrumbSeparator.displayName = "BreadcrumbSeparator"

const BreadcrumbEllipsis = ({ className, ...props }) => (
    <span
        role="presentation"
        aria-hidden="true"
        className={cn("flex size-9 items-center justify-center", className)}
        {...props}
    >
        <MoreHorizontal className="size-4" aria-hidden="true" />
        <span className="sr-only">Mas</span>
    </span>
)
BreadcrumbEllipsis.displayName = "BreadcrumbEllipsis"

export {
    Breadcrumb,
    BreadcrumbEllipsis,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
}
