import { Button } from '@/Components/ui/button';

export default function ConversionActionBar({
    busy = false,
    busyLabel,
    children,
    disabled = false,
    idleLabel,
    icon: Icon,
    processingIcon: ProcessingIcon,
    style,
    testId,
    type = 'submit',
    ...props
}) {
    const VisibleIcon = busy ? ProcessingIcon : Icon;

    return (
        <div className="mt-6 flex flex-col gap-4">
            <Button
                type={type}
                disabled={disabled}
                data-testid={testId}
                className="h-12 w-full gap-2 rounded-lg font-bold text-white hover:opacity-90"
                style={style}
                aria-label={busy ? busyLabel : idleLabel}
                aria-busy={busy}
                {...props}
            >
                {VisibleIcon ? (
                    <VisibleIcon className={busy ? 'h-5 w-5 animate-spin' : 'h-5 w-5'} data-icon aria-hidden="true" />
                ) : null}
                {busy ? busyLabel : idleLabel}
            </Button>

            {children ? (
                <p className="text-center text-xs text-muted-foreground">
                    {children}
                </p>
            ) : null}
        </div>
    );
}
