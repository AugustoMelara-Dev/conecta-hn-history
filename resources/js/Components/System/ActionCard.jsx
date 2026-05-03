import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/Components/ui/card";
import { cn } from "@/lib/utils";

export default function ActionCard({ title, description, action, children, className }) {
    return (
        <Card className={cn("border-border/80", className)}>
            <CardHeader>
                <CardTitle className="text-base">{title}</CardTitle>
                {description ? <CardDescription>{description}</CardDescription> : null}
            </CardHeader>
            <CardContent>{children}</CardContent>
            {action ? <CardFooter>{action}</CardFooter> : null}
        </Card>
    );
}
