import { Button } from '@/Components/ui/button';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';
import { useForm } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdatePasswordForm({ className = '' }) {
    const passwordInput = useRef();
    const currentPasswordInput = useRef();
    const passwordConfirmationInput = useRef();

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

    const updatePassword = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current.focus();
                    return;
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current.focus();
                    return;
                }

                if (errors.password_confirmation) {
                    reset('password_confirmation');
                    passwordConfirmationInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-semibold text-foreground">
                    Actualizar contraseña
                </h2>

                <p className="mt-1 text-sm text-muted-foreground">
                    Usa una contraseña larga y única para mantener tu cuenta protegida.
                </p>
            </header>

            <form onSubmit={updatePassword} className="mt-6">
                <FieldGroup>
                    <Field data-invalid={Boolean(errors.current_password)}>
                        <FieldLabel htmlFor="current_password">Contraseña actual</FieldLabel>
                        <Input
                        id="current_password"
                        name="current_password"
                        ref={currentPasswordInput}
                        value={data.current_password}
                        onChange={(e) =>
                            setData('current_password', e.target.value)
                        }
                        type="password"
                        autoComplete="current-password"
                        aria-invalid={Boolean(errors.current_password)}
                        aria-describedby={errors.current_password ? 'current-password-error' : undefined}
                    />
                        {errors.current_password ? (
                            <FieldDescription id="current-password-error" role="alert" className="text-destructive">
                                {errors.current_password}
                            </FieldDescription>
                        ) : null}
                    </Field>

                    <Field data-invalid={Boolean(errors.password)}>
                        <FieldLabel htmlFor="password">Nueva contraseña</FieldLabel>
                        <Input
                        id="password"
                        name="password"
                        ref={passwordInput}
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        type="password"
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={errors.password ? 'password-error' : undefined}
                    />
                        {errors.password ? (
                            <FieldDescription id="password-error" role="alert" className="text-destructive">
                                {errors.password}
                            </FieldDescription>
                        ) : null}
                    </Field>

                    <Field data-invalid={Boolean(errors.password_confirmation)}>
                        <FieldLabel htmlFor="password_confirmation">Confirmar contraseña</FieldLabel>
                        <Input
                        id="password_confirmation"
                        name="password_confirmation"
                        ref={passwordConfirmationInput}
                        value={data.password_confirmation}
                        onChange={(e) =>
                            setData('password_confirmation', e.target.value)
                        }
                        type="password"
                        autoComplete="new-password"
                        aria-invalid={Boolean(errors.password_confirmation)}
                        aria-describedby={errors.password_confirmation ? 'password-confirmation-error' : undefined}
                    />
                        {errors.password_confirmation ? (
                            <FieldDescription id="password-confirmation-error" role="alert" className="text-destructive">
                                {errors.password_confirmation}
                            </FieldDescription>
                        ) : null}
                    </Field>

                <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                            Guardar contraseña
                        </Button>

                        <p className="text-sm text-muted-foreground" aria-live="polite">
                            {recentlySuccessful ? 'Contraseña actualizada.' : ''}
                        </p>
                </div>
                </FieldGroup>
            </form>
        </section>
    );
}
