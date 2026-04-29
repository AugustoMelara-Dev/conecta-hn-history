import * as React from "react"

import { cn } from "@/lib/utils"

const TooltipContext = React.createContext(null)

function TooltipProvider({ children }) {
    return children
}

function Tooltip({ children, delayDuration: _delayDuration }) {
    const [open, setOpen] = React.useState(false)
    const value = React.useMemo(() => ({ open, setOpen }), [open])

    return <TooltipContext.Provider value={value}>{children}</TooltipContext.Provider>
}

function useTooltip() {
    const context = React.useContext(TooltipContext)

    if (!context) {
        throw new Error("Tooltip components must be used within Tooltip")
    }

    return context
}

const TooltipTrigger = React.forwardRef(({ className, children, ...props }, ref) => {
    const { setOpen } = useTooltip()

    return React.cloneElement(React.Children.only(children), {
        ref,
        className: cn(children.props.className, className),
        onMouseEnter: (event) => {
            children.props.onMouseEnter?.(event)
            setOpen(true)
        },
        onMouseLeave: (event) => {
            children.props.onMouseLeave?.(event)
            setOpen(false)
        },
        onFocus: (event) => {
            children.props.onFocus?.(event)
            setOpen(true)
        },
        onBlur: (event) => {
            children.props.onBlur?.(event)
            setOpen(false)
        },
        ...props,
    })
})
TooltipTrigger.displayName = "TooltipTrigger"

const TooltipContent = React.forwardRef(({ className, side: _side = "top", sideOffset: _sideOffset = 4, ...props }, ref) => {
    const { open } = useTooltip()

    if (!open) {
        return null
    }

    return (
        <div
            ref={ref}
            role="tooltip"
            className={cn("absolute z-50 mt-2 max-w-xs rounded-md bg-popover px-3 py-1.5 text-xs text-popover-foreground shadow-md ring-1 ring-border", className)}
            {...props}
        />
    )
})
TooltipContent.displayName = "TooltipContent"

export { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger }
