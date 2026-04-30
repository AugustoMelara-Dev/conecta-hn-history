import { Alert, AlertDescription } from '@/Components/ui/alert';
import { Button } from '@/Components/ui/button';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';
import { Link, useForm, usePage } from '@inertiajs/react';
import { useRef } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;
    const nameInput = useRef(null);
    const emailInput = useRef(null);

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'), {
            preserveScroll: true,
            onError: (errors) => {
                if (errors.name) {
                    nameInput.current?.focus();
                    return;
                }

                if (errors.email) {
                    emailInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-semibold text-foreground">
                    Información del perfil
                </h2>

                <p className="mt-1 text-sm text-muted-foreground">
                    Actualiza el nombre y correo asociado a tu cuenta.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6">
                <FieldGroup>
                    <Field data-invalid={Boolean(errors.name)}>
                        <FieldLabel htmlFor="name">Nombre</FieldLabel>
                        <Input
                        id="name"
                        name="name"
                        ref={nameInput}
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        autoComplete="name"
                        aria-invalid={Boolean(errors.name)}
                        aria-describedby={errors.name ? 'profile-name-error' : undefined}
                    />
                        {errors.name ? (
                            <FieldDescription id="profile-name-error" role="alert" className="text-destructive">
                                {errors.name}
                            </FieldDescription>
                        ) : null}
                    </Field>

                    <Field data-invalid={Boolean(errors.email)}>
                        <FieldLabel htmlFor="email">Correo electrónico</FieldLabel>
                        <Input
                        id="email"
                        name="email"
                        ref={emailInput}
                        type="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                        spellCheck={false}
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? 'profile-email-error' : undefined}
                    />
                        {errors.email ? (
                            <FieldDescription id="profile-email-error" role="alert" className="text-destructive">
                                {errors.email}
                            </FieldDescription>
                        ) : null}
                    </Field>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <Alert>
                        <AlertDescription>
                            Tu correo aún no está verificado.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="ml-1 font-semibold underline underline-offset-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                Reenviar verificación.
                            </Link>
                        </AlertDescription>

                        {status === 'verification-link-sent' && (
                            <AlertDescription className="mt-2" aria-live="polite">
                                Enviamos un nuevo enlace de verificación a tu correo.
                            </AlertDescription>
                        )}
                    </Alert>
                )}

                <div className="flex items-center gap-4">
                        <Button type="submit" disabled={processing}>
                            {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                            Guardar perfil
                        </Button>

                        <p className="text-sm text-muted-foreground" aria-live="polite">
                            {recentlySuccessful ? 'Perfil guardado.' : ''}
                        </p>
                </div>
                </FieldGroup>
            </form>
        </section>
    );
}
