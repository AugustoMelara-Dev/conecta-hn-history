import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { User, Lock, AlertTriangle } from 'lucide-react';

export default function Edit({ mustVerifyEmail, status }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-foreground">
                    Mi Perfil
                </h2>
            }
        >
            <Head title="Perfil" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-6">

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Left Column: Personal Info & Password */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Profile Info Card */}
                            <Card className="shadow-sm border-border/50">
                                <CardHeader>
                                    <div className="flex items-center gap-2 mb-1">
                                        <div className="p-2 bg-primary/10 rounded-lg">
                                            <User className="w-5 h-5 text-primary" />
                                        </div>
                                        <CardTitle>Información Personal</CardTitle>
                                    </div>
                                    <CardDescription>
                                        Actualiza tu nombre y dirección de correo electrónico.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <UpdateProfileInformationForm
                                        mustVerifyEmail={mustVerifyEmail}
                                        status={status}
                                        className="max-w-xl"
                                    />
                                </CardContent>
                            </Card>

                            {/* Password Card */}
                            <Card className="shadow-sm border-border/50">
                                <CardHeader>
                                    <div className="flex items-center gap-2 mb-1">
                                        <div className="p-2 bg-primary/10 rounded-lg">
                                            <Lock className="w-5 h-5 text-primary" />
                                        </div>
                                        <CardTitle>Seguridad</CardTitle>
                                    </div>
                                    <CardDescription>
                                        Asegura tu cuenta con una contraseña robusta.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <UpdatePasswordForm className="max-w-xl" />
                                </CardContent>
                            </Card>
                        </div>

                        {/* Right Column: Danger Zone */}
                        <div className="lg:col-span-1">
                            <Card className="shadow-sm border-destructive/20 bg-destructive/5">
                                <CardHeader>
                                    <div className="flex items-center gap-2 mb-1">
                                        <div className="p-2 bg-destructive/10 rounded-lg">
                                            <AlertTriangle className="w-5 h-5 text-destructive" />
                                        </div>
                                        <CardTitle className="text-destructive">Zona de Peligro</CardTitle>
                                    </div>
                                    <CardDescription className="text-destructive/80">
                                        Una vez que elimines tu cuenta, no hay vuelta atrás. Por favor, ten cuidado.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <DeleteUserForm className="max-w-full" />
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
