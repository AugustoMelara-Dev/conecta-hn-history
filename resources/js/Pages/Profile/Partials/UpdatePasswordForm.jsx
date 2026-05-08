import { useForm } from '@inertiajs/react';
import { CheckCircle2, KeyRound } from 'lucide-react';
import { useRef } from 'react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';

export default function UpdatePasswordForm() {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword = (event) => {
        event.preventDefault();

        put('/password', {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (formErrors) => {
                if (formErrors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (formErrors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <form onSubmit={updatePassword} data-testid="profile-password-form">
            <FieldGroup>
                <Field data-invalid={Boolean(errors.current_password)}>
                    <FieldLabel htmlFor="current-password">Contrasena actual</FieldLabel>
                    <Input
                        id="current-password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(event) => setData('current_password', event.target.value)}
                        type="password"
                        autoComplete="current-password"
                        aria-invalid={Boolean(errors.current_password)}
                        aria-describedby={errors.current_password ? 'current-password-error' : undefined}
                        data-testid="profile-current-password-input"
                    />
                    {errors.current_password ? (
                        <FieldDescription id="current-password-error" role="alert" className="text-destructive">
                            {errors.current_password}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field data-invalid={Boolean(errors.password)}>
                    <FieldLabel htmlFor="new-password">Nueva contrasena</FieldLabel>
                    <Input
                        id="new-password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(event) => setData('password', event.target.value)}
                        type="password"
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'new-password-error' : 'new-password-help'}
                        data-testid="profile-new-password-input"
                    />
                    {errors.password ? (
                        <FieldDescription id="new-password-error" role="alert" className="text-destructive">
                            {errors.password}
                        </FieldDescription>
                    ) : (
                        <FieldDescription id="new-password-help">
                            Usa una clave nueva que no compartas en otros servicios.
                        </FieldDescription>
                    )}
                </Field>

                <Field data-invalid={Boolean(errors.password_confirmation)}>
                    <FieldLabel htmlFor="password-confirmation">Confirmar nueva contrasena</FieldLabel>
                    <Input
                        id="password-confirmation"
                        value={data.password_confirmation}
                        onChange={(event) => setData('password_confirmation', event.target.value)}
                        type="password"
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password_confirmation)}
                        aria-describedby={errors.password_confirmation ? 'password-confirmation-error' : undefined}
                        data-testid="profile-password-confirmation-input"
                    />
                    {errors.password_confirmation ? (
                        <FieldDescription id="password-confirmation-error" role="alert" className="text-destructive">
                            {errors.password_confirmation}
                        </FieldDescription>
                    ) : null}
                </Field>

                {recentlySuccessful ? (
                    <Alert variant="success" aria-live="polite">
                        <CheckCircle2 aria-hidden="true" />
                        <AlertTitle>Contrasena actualizada</AlertTitle>
                        <AlertDescription>
                            Tu nueva contrasena ya esta activa.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing} data-testid="profile-password-save">
                        {processing ? <Spinner data-icon="inline-start" /> : <KeyRound data-icon="inline-start" aria-hidden="true" />}
                        Guardar contrasena
                    </Button>
                </div>
            </FieldGroup>
        </form>
    );
}
