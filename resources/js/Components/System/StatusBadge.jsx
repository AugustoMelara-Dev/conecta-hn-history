import { Badge } from "@/Components/ui/badge";
import { cn } from "@/lib/utils";

const variants = {
    approved: { variant: "outline", className: "border-emerald-200 bg-emerald-100 text-emerald-800" },
    active: { variant: "outline", className: "border-emerald-200 bg-emerald-100 text-emerald-800" },
    pending: { variant: "outline", className: "border-amber-200 bg-amber-100 text-amber-900" },
    warning: { variant: "outline", className: "border-amber-200 bg-amber-100 text-amber-900" },
    rejected: { variant: "outline", className: "border-red-200 bg-red-100 text-red-800" },
    error: { variant: "outline", className: "border-red-200 bg-red-100 text-red-800" },
};

export default function StatusBadge({ status, label }) {
    const normalized = String(status || "pending").toLowerCase();
    const config = variants[normalized] || { variant: "outline", className: "border-slate-200 bg-slate-100 text-slate-700" };

    return (
        <Badge variant={config.variant} className={cn(config.className)}>
            {label || normalized}
        </Badge>
    );
}
