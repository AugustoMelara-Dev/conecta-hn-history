import * as React from "react"

import { cn } from "@/lib/utils"

const Progress = React.forwardRef(({ className, value = 0, max = 100, "aria-label": ariaLabel = "Progreso", ...props }, ref) => {
    const safeMax = Number(max) > 0 ? Number(max) : 100
    const safeValue = Math.min(Math.max(Number(value) || 0, 0), safeMax)
    const percentage = (safeValue / safeMax) * 100

    return (
        <div
            ref={ref}
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={safeMax}
            aria-valuenow={safeValue}
            aria-label={ariaLabel}
            className={cn("relative h-2 w-full overflow-hidden rounded-full bg-secondary", className)}
            {...props}
        >
            <div
                className="h-full w-full flex-1 bg-primary transition-transform duration-normal ease-out motion-reduce:transition-none"
                style={{ transform: `scaleX(${percentage / 100})`, transformOrigin: "left" }}
            />
        </div>
    )
})
Progress.displayName = "Progress"

export { Progress }
