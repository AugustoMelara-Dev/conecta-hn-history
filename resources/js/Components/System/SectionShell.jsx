import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card";
import { cn } from "@/lib/utils";

export default function SectionShell({ title, description, className, children, contentClassName }) {
    return (
        <Card className={cn("border-border/80", className)}>
            {(title || description) ? (
                <CardHeader>
                    {title ? <CardTitle className="text-base">{title}</CardTitle> : null}
                    {description ? <CardDescription>{description}</CardDescription> : null}
                </CardHeader>
            ) : null}
            <CardContent className={cn(title || description ? "pt-0" : "pt-6", contentClassName)}>{children}</CardContent>
        </Card>
    );
}
