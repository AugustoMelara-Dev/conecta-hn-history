import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { cn } from "@/lib/utils";

export default function MetricCard({ title, value, hint, icon: Icon, className }) {
    return (
        <Card className={cn("border-border/80", className)}>
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center justify-between gap-2 text-sm font-medium text-muted-foreground">
                    <span>{title}</span>
                    {Icon ? <Icon className="h-4 w-4 text-muted-foreground" aria-hidden="true" /> : null}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <p className="text-2xl font-semibold tracking-tight">{value}</p>
                {hint ? <p className="mt-1 text-xs text-muted-foreground">{hint}</p> : null}
            </CardContent>
        </Card>
    );
}
