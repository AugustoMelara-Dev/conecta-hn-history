import { Link, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, MailWarning, Save } from 'lucide-react';

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

function safeRoutePath(name, fallbackPath) {
    try {
        if (route().has(name)) {
            return new URL(route(name), window.location.origin).pathname;
        }
    } catch {
        return fallbackPath;
    }

    return fallbackPath;
}

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
        });

    const submit = (event) => {
        event.preventDefault();

        patch(safeRoutePath('mi-cuenta.update', '/mi-cuenta'), {
            preserveScroll: true,
        });
    };

    return (
        <form onSubmit={submit} data-testid="profile-info-form">
            <FieldGroup>
                <Field data-invalid={Boolean(errors.name)}>
                    <FieldLabel htmlFor="profile-name">Nombre</FieldLabel>
                    <Input
                        id="profile-name"
                        value={data.name}
                        onChange={(event) => setData('name', event.target.value)}
                        required
                        autoComplete="name"
                        aria-invalid={Boolean(errors.name)}
                        aria-describedby={errors.name ? 'profile-name-error' : undefined}
                        data-testid="profile-name-input"
                    />
                    {errors.name ? (
                        <FieldDescription id="profile-name-error" role="alert" className="text-destructive">
                            {errors.name}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field data-invalid={Boolean(errors.email)}>
                    <FieldLabel htmlFor="profile-email">Correo electronico</FieldLabel>
                    <Input
                        id="profile-email"
                        type="email"
                        value={data.email}
                        onChange={(event) => setData('email', event.target.value)}
                        required
                        autoComplete="username"
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? 'profile-email-error' : undefined}
                        data-testid="profile-email-input"
                    />
                    {errors.email ? (
                        <FieldDescription id="profile-email-error" role="alert" className="text-destructive">
                            {errors.email}
                        </FieldDescription>
                    ) : null}
                </Field>

                {mustVerifyEmail && user.email_verified_at === null ? (
                    <Alert variant={status === 'verification-link-sent' ? 'success' : 'warning'} aria-live="polite">
                        <MailWarning aria-hidden="true" />
                        <AlertTitle>Correo pendiente de verificacion</AlertTitle>
                        <AlertDescription>
                            Tu correo aun no esta verificado.{' '}
                            <Button variant="link" className="h-auto p-0" asChild>
                                <Link href={route('verification.send')} method="post" as="button">
                                    Reenviar correo de verificacion
                                </Link>
                            </Button>
                            {status === 'verification-link-sent' ? (
                                <span className="mt-2 block">
                                    Enviamos un nuevo enlace de verificacion a tu correo.
                                </span>
                            ) : null}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {recentlySuccessful ? (
                    <Alert variant="success" aria-live="polite">
                        <CheckCircle2 aria-hidden="true" />
                        <AlertTitle>Cambios guardados</AlertTitle>
                        <AlertDescription>
                            Tus datos de perfil se actualizaron correctamente.
                        </AlertDescription>
                    </Alert>
                ) : null}

                <div className="flex justify-end">
                    <Button type="submit" disabled={processing} data-testid="profile-save">
                        {processing ? <Spinner data-icon="inline-start" /> : <Save data-icon="inline-start" aria-hidden="true" />}
                        Guardar cambios
                    </Button>
                </div>
            </FieldGroup>
        </form>
    );
}
