import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';
import { Mail, ShieldCheck, Trash2, UserRound } from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

function SectionIcon({ children }) {
    return (
        <div className="flex size-10 shrink-0 items-center justify-center rounded-lg border bg-background text-muted-foreground">
            {children}
        </div>
    );
}

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const emailVerified = Boolean(user.email_verified_at);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-2">
                    <Badge className="w-fit" variant="secondary">
                        Mi cuenta
                    </Badge>
                    <h1 className="text-2xl font-semibold tracking-normal text-foreground">
                        Perfil y seguridad
                    </h1>
                    <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                        Administra tus datos personales, el correo de acceso y la seguridad de tu cuenta.
                    </p>
                </div>
            }
        >
            <Head title="Mi cuenta" />

            <main className="bg-background py-10" data-testid="profile-page">
                <div className="mx-auto flex max-w-6xl flex-col gap-6 px-4 sm:px-6 lg:px-8">
                    <Card className="overflow-hidden">
                        <CardHeader className="gap-5 border-b bg-muted/30">
                            <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                                <div className="flex items-start gap-4">
                                    <div className="flex size-14 items-center justify-center rounded-xl border bg-background text-muted-foreground">
                                        <UserRound aria-hidden="true" />
                                    </div>
                                    <div className="flex min-w-0 flex-col gap-2">
                                        <CardTitle className="text-2xl">{user.name}</CardTitle>
                                        <CardDescription className="flex items-center gap-2 break-all">
                                            <Mail aria-hidden="true" />
                                            {user.email}
                                        </CardDescription>
                                    </div>
                                </div>

                                <div className="flex flex-wrap gap-2">
                                    <Badge variant={emailVerified ? 'default' : 'secondary'}>
                                        {emailVerified ? 'Correo verificado' : 'Correo pendiente'}
                                    </Badge>
                                    <Badge variant="outline">Acceso personal</Badge>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent className="grid gap-4 p-5 sm:grid-cols-3 sm:p-6">
                            <div className="flex flex-col gap-1 rounded-lg border bg-card p-4">
                                <span className="text-sm font-medium text-foreground">Identidad</span>
                                <span className="text-sm text-muted-foreground">Nombre y correo de acceso.</span>
                            </div>
                            <div className="flex flex-col gap-1 rounded-lg border bg-card p-4">
                                <span className="text-sm font-medium text-foreground">Verificacion</span>
                                <span className="text-sm text-muted-foreground">
                                    {emailVerified ? 'Lista para recibir avisos.' : 'Pendiente de confirmar.'}
                                </span>
                            </div>
                            <div className="flex flex-col gap-1 rounded-lg border bg-card p-4">
                                <span className="text-sm font-medium text-foreground">Seguridad</span>
                                <span className="text-sm text-muted-foreground">Contrasena y proteccion de cuenta.</span>
                            </div>
                        </CardContent>
                    </Card>

                    {!emailVerified && mustVerifyEmail ? (
                        <Alert variant="warning" aria-live="polite">
                            <Mail aria-hidden="true" />
                            <AlertTitle>Correo pendiente de verificacion</AlertTitle>
                            <AlertDescription>
                                Puedes seguir editando tu perfil, pero conviene confirmar tu correo para recibir avisos de acceso y seguridad.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.72fr)]">
                        <div className="flex flex-col gap-6">
                            <Card>
                                <CardHeader>
                                    <div className="flex items-start gap-3">
                                        <SectionIcon>
                                            <UserRound aria-hidden="true" />
                                        </SectionIcon>
                                        <div className="flex flex-col gap-1">
                                            <CardTitle>Datos de perfil</CardTitle>
                                            <CardDescription>
                                                Actualiza el nombre visible y el correo asociado a tu cuenta.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <UpdateProfileInformationForm
                                        mustVerifyEmail={mustVerifyEmail}
                                        status={status}
                                    />
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-start gap-3">
                                        <SectionIcon>
                                            <ShieldCheck aria-hidden="true" />
                                        </SectionIcon>
                                        <div className="flex flex-col gap-1">
                                            <CardTitle>Seguridad</CardTitle>
                                            <CardDescription>
                                                Cambia tu contrasena por una clave fuerte y unica.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <UpdatePasswordForm />
                                </CardContent>
                            </Card>
                        </div>

                        <aside className="flex flex-col gap-6">
                            <Card>
                                <CardHeader>
                                    <div className="flex items-start gap-3">
                                        <SectionIcon>
                                            <ShieldCheck aria-hidden="true" />
                                        </SectionIcon>
                                        <div className="flex flex-col gap-1">
                                            <CardTitle>Estado de la cuenta</CardTitle>
                                            <CardDescription>
                                                Revisa lo esencial antes de cambiar datos sensibles.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent className="flex flex-col gap-4">
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">Correo</span>
                                        <Badge variant={emailVerified ? 'default' : 'secondary'}>
                                            {emailVerified ? 'Verificado' : 'Pendiente'}
                                        </Badge>
                                    </div>
                                    <Separator />
                                    <div className="flex items-center justify-between gap-4">
                                        <span className="text-sm text-muted-foreground">Sesion</span>
                                        <Badge variant="outline">Activa</Badge>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardHeader>
                                    <div className="flex items-start gap-3">
                                        <SectionIcon>
                                            <Trash2 aria-hidden="true" />
                                        </SectionIcon>
                                        <div className="flex flex-col gap-1">
                                            <CardTitle>Eliminar cuenta</CardTitle>
                                            <CardDescription>
                                                Esta accion cierra la cuenta y elimina los datos asociados de forma permanente.
                                            </CardDescription>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <DeleteUserForm />
                                </CardContent>
                            </Card>
                        </aside>
                    </div>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
