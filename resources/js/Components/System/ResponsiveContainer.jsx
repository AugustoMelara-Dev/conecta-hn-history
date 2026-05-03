import { cn } from "@/lib/utils";

export default function ResponsiveContainer({ className, size = "4xl", paddedY = false, children }) {
    const maxWidth = {
        "3xl": "max-w-3xl",
        "4xl": "max-w-4xl",
        "5xl": "max-w-5xl",
        "6xl": "max-w-6xl",
    }[size] || "max-w-4xl";

    return <div className={cn("mx-auto w-full px-4 sm:px-6 lg:px-8", paddedY && "py-6 lg:py-8", maxWidth, className)}>{children}</div>;
}
