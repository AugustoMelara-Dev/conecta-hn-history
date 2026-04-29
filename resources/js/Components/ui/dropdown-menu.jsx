import * as React from "react"

import { cn } from "@/lib/utils"

const DropdownMenuContext = React.createContext(null)

function DropdownMenu({ children }) {
    const [open, setOpen] = React.useState(false)
    const rootRef = React.useRef(null)

    React.useEffect(() => {
        if (!open) return undefined

        const handlePointerDown = (event) => {
            if (rootRef.current && !rootRef.current.contains(event.target)) {
                setOpen(false)
            }
        }

        document.addEventListener("mousedown", handlePointerDown)
        return () => document.removeEventListener("mousedown", handlePointerDown)
    }, [open])

    const value = React.useMemo(() => ({ open, setOpen }), [open])

    return (
        <DropdownMenuContext.Provider value={value}>
            <div ref={rootRef} className="relative inline-block text-left">
                {children}
            </div>
        </DropdownMenuContext.Provider>
    )
}

function useDropdownMenu() {
    const context = React.useContext(DropdownMenuContext)

    if (!context) {
        throw new Error("DropdownMenu components must be used within DropdownMenu")
    }

    return context
}

const DropdownMenuTrigger = React.forwardRef(({ children, asChild = false, ...props }, ref) => {
    const { open, setOpen } = useDropdownMenu()
    const triggerProps = {
        ref,
        "aria-haspopup": "menu",
        "aria-expanded": open,
        onClick: (event) => {
            children?.props?.onClick?.(event)
            setOpen(!open)
        },
        ...props,
    }

    if (asChild) {
        return React.cloneElement(React.Children.only(children), triggerProps)
    }

    return <button type="button" {...triggerProps}>{children}</button>
})
DropdownMenuTrigger.displayName = "DropdownMenuTrigger"

const DropdownMenuContent = React.forwardRef(({ className, align = "end", ...props }, ref) => {
    const { open } = useDropdownMenu()

    if (!open) {
        return null
    }

    return (
        <div
            ref={ref}
            role="menu"
            className={cn(
                "absolute z-50 mt-2 min-w-44 rounded-md border bg-popover p-1 text-popover-foreground shadow-md",
                align === "end" ? "right-0" : "left-0",
                className
            )}
            {...props}
        />
    )
})
DropdownMenuContent.displayName = "DropdownMenuContent"

const DropdownMenuItem = React.forwardRef(({ className, inset, ...props }, ref) => {
    const { setOpen } = useDropdownMenu()

    return (
        <button
            ref={ref}
            type="button"
            role="menuitem"
            className={cn(
                "relative flex w-full cursor-default select-none items-center rounded-sm px-2 py-1.5 text-left text-sm outline-none transition-colors hover:bg-accent hover:text-accent-foreground focus:bg-accent focus:text-accent-foreground",
                inset && "pl-8",
                className
            )}
            onClick={(event) => {
                props.onClick?.(event)
                setOpen(false)
            }}
            {...props}
        />
    )
})
DropdownMenuItem.displayName = "DropdownMenuItem"

const DropdownMenuLabel = React.forwardRef(({ className, inset, ...props }, ref) => (
    <div ref={ref} className={cn("px-2 py-1.5 text-sm font-semibold", inset && "pl-8", className)} {...props} />
))
DropdownMenuLabel.displayName = "DropdownMenuLabel"

const DropdownMenuSeparator = React.forwardRef(({ className, ...props }, ref) => (
    <div ref={ref} className={cn("-mx-1 my-1 h-px bg-muted", className)} {...props} />
))
DropdownMenuSeparator.displayName = "DropdownMenuSeparator"

const DropdownMenuGroup = ({ ...props }) => <div role="group" {...props} />
DropdownMenuGroup.displayName = "DropdownMenuGroup"

export {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
}
