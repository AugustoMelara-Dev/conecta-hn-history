import { AlertCircle } from 'lucide-react';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';

export function focusFirstInvalidField(root = document) {
    if (typeof document === 'undefined') {
        return;
    }

    const container = root || document;
    const target = container.querySelector(
        [
            '[aria-invalid="true"]:not([disabled])',
            '[data-invalid="true"] input:not([disabled])',
            '[data-invalid="true"] textarea:not([disabled])',
            '[data-invalid="true"] button:not([disabled])',
            '[data-invalid="true"] [tabindex]:not([tabindex="-1"])',
        ].join(', ')
    );

    if (!target) {
        return;
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    window.requestAnimationFrame(() => target.focus({ preventScroll: true }));
}

export function FormRecoveryAlert({
    title = 'Revisa estos datos',
    description = 'Corrige los campos marcados para continuar.',
    messages = [],
    onFocusFirstError,
    testId,
}) {
    const visibleMessages = messages.filter(Boolean);

    if (visibleMessages.length === 0) {
        return null;
    }

    return (
        <Alert
            variant="destructive"
            role="alert"
            aria-live="assertive"
            data-testid={testId}
        >
            <AlertCircle className="h-4 w-4" aria-hidden="true" />
            <AlertTitle>{title}</AlertTitle>
            <AlertDescription className="space-y-3">
                <p>{description}</p>
                <ul className="list-disc space-y-1 pl-5">
                    {visibleMessages.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
                {onFocusFirstError ? (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={onFocusFirstError}
                        className="border-destructive/30 bg-background text-destructive hover:bg-destructive/10"
                    >
                        Ir al primer campo
                    </Button>
                ) : null}
            </AlertDescription>
        </Alert>
    );
}
