import * as React from "react"

import { Input } from "@/Components/ui/input"
import { cn } from "@/lib/utils"

const InputOTP = React.forwardRef(({
    value = "",
    onChange,
    maxLength = 6,
    className,
    ...props
}, ref) => {
    const normalizedValue = String(value ?? "").replace(/\D/g, "").slice(0, maxLength)

    return (
        <Input
            ref={ref}
            inputMode="numeric"
            autoComplete="one-time-code"
            pattern="[0-9]*"
            maxLength={maxLength}
            value={normalizedValue}
            onChange={(event) => {
                const nextValue = event.target.value.replace(/\D/g, "").slice(0, maxLength)
                onChange?.(nextValue)
            }}
            className={cn(
                "h-12 text-center font-mono text-2xl font-semibold tracking-[0.35em]",
                className
            )}
            {...props}
        />
    )
})
InputOTP.displayName = "InputOTP"

export { InputOTP }
