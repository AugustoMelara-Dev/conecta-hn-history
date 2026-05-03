import { Button } from "@/Components/ui/button";
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyIcon, EmptyTitle } from "@/Components/ui/empty";
import { Link } from "@inertiajs/react";
import { Inbox } from "lucide-react";

export default function EmptyState({
    title,
    description,
    icon: Icon = Inbox,
    action,
    actionLabel,
    actionHref,
    onAction,
}) {
    return (
        <Empty>
            <EmptyHeader>
                <EmptyIcon icon={Icon} />
                <EmptyTitle>{title}</EmptyTitle>
                {description ? <EmptyDescription>{description}</EmptyDescription> : null}
            </EmptyHeader>
            {action ? <EmptyContent>{action}</EmptyContent> : null}
            {!action && actionLabel ? (
                <EmptyContent>
                    {actionHref ? (
                        <Button asChild>
                            <Link href={actionHref}>{actionLabel}</Link>
                        </Button>
                    ) : (
                        <Button type="button" onClick={onAction}>{actionLabel}</Button>
                    )}
                </EmptyContent>
            ) : null}
        </Empty>
    );
}
