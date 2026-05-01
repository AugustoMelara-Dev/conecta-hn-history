import { Link } from '@inertiajs/react';
import { PackageOpen } from 'lucide-react';

import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyIcon,
    EmptyTitle,
} from '@/Components/ui/empty';
import { Button } from '@/Components/ui/button';

export default function EntityEmptyState({
    icon = PackageOpen,
    title,
    description,
    actionHref,
    actionLabel,
    secondaryAction,
}) {
    return (
        <Empty>
            <EmptyHeader>
                <EmptyIcon icon={icon} />
                <EmptyTitle>{title}</EmptyTitle>
                <EmptyDescription>{description}</EmptyDescription>
            </EmptyHeader>
            {(actionHref && actionLabel) || secondaryAction ? (
                <EmptyContent>
                    {actionHref && actionLabel ? (
                        <Button asChild>
                            <Link href={actionHref}>{actionLabel}</Link>
                        </Button>
                    ) : null}
                    {secondaryAction}
                </EmptyContent>
            ) : null}
        </Empty>
    );
}
