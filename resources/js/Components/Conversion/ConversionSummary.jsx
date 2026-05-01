import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Progress } from '@/Components/ui/progress';
import { cn } from '@/lib/utils';

export default function ConversionSummary({
    children,
    className,
    icon: Icon,
    progressLabel,
    progressStatus,
    progressValue,
    sticky = true,
    title,
    totals,
}) {
    return (
        <Card className={cn(sticky ? 'sticky top-40' : null, className)}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-lg">
                    {Icon ? <Icon className="h-5 w-5 text-[color:var(--checkout-accent)]" data-icon aria-hidden="true" /> : null}
                    {title}
                </CardTitle>
            </CardHeader>
            <CardContent>
                {children}

                {totals?.length ? (
                    <div className="mt-4 grid gap-2 border-t border-border pt-4">
                        {totals.map((total) => (
                            <div
                                key={total.label}
                                className={total.emphasis
                                    ? 'flex justify-between text-xl font-bold text-foreground'
                                    : 'flex justify-between text-muted-foreground'}
                            >
                                <span>{total.label}</span>
                                <span className={total.accent ? 'text-[color:var(--checkout-accent)]' : undefined}>
                                    {total.value}
                                </span>
                            </div>
                        ))}
                    </div>
                ) : null}

                {typeof progressValue === 'number' ? (
                    <div className="mt-4" aria-live="polite">
                        <div className="mb-2 flex items-center justify-between text-xs text-muted-foreground">
                            <span>{progressLabel}</span>
                            <span>{progressStatus}</span>
                        </div>
                        <Progress value={progressValue} />
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
