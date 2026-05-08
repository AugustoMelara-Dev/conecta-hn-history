import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Building2,
    Compass,
    Mail,
    Store,
    UserRound,
} from 'lucide-react';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Avatar, AvatarFallback } from '@/Components/ui/avatar';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';

const actionCards = [
    {
        title: 'Mi cuenta',
        description: 'Actualiza tu nombre, correo y seguridad personal.',
        href: '/mi-cuenta',
        testId: 'dashboard-profile-cta',
        icon: UserRound,
        button: 'Gestionar cuenta',
    },
    {
        title: 'Explorar negocios',
        description: 'Busca comercios locales, servicios y micrositios activos.',
        href: '/directorio',
        testId: 'dashboard-directory-cta',
        icon: Compass,
        button: 'Ir al directorio',
    },
];

function initials(name = '') {
    const parts = name
        .split(' ')
        .map((part) => part.trim())
        .filter(Boolean);

    return (parts[0]?.[0] || 'C') + (parts[1]?.[0] || '');
}

function panelLabel(panelUrl) {
    return panelUrl && !panelUrl.includes('/registrar-negocio') ? 'Abrir panel' : 'Registrar negocio';
}

export default function Dashboard() {
    const { auth } = usePage().props;
    const user = auth?.user;
    const panelUrl = auth?.panel_url || user?.panel_url || '/registrar-negocio';
    const panelText = panelLabel(panelUrl);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-1">
                    <p className="text-sm font-medium text-muted-foreground">Inicio</p>
                    <h2 className="text-2xl font-semibold tracking-normal text-foreground">
                        Panel de acceso
                    </h2>
                </div>
            }
        >
            <Head title="Panel de acceso" />

            <main className="bg-background py-10" data-testid="dashboard-page">
                <div className="mx-auto flex max-w-5xl flex-col gap-8 px-4 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader className="flex flex-col gap-6 sm:flex-row sm:items-start sm:justify-between">
                            <div className="flex items-start gap-4">
                                <Avatar className="size-14 rounded-lg">
                                    <AvatarFallback className="rounded-lg text-base font-semibold">
                                        {initials(user?.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <div className="flex min-w-0 flex-col gap-2">
                                    <Badge variant="secondary" className="w-fit">
                                        Sesion activa
                                    </Badge>
                                    <div className="flex flex-col gap-1">
                                        <CardTitle>Hola, {user?.name || 'bienvenido'}</CardTitle>
                                        <CardDescription className="flex items-center gap-2 break-all">
                                            <Mail aria-hidden="true" />
                                            {user?.email || 'Correo no disponible'}
                                        </CardDescription>
                                    </div>
                                </div>
                            </div>
                            <Button asChild data-testid="dashboard-panel-cta">
                                <Link href={panelUrl}>
                                    {panelText}
                                    <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                </Link>
                            </Button>
                        </CardHeader>
                        <CardContent>
                            <Alert>
                                <Building2 aria-hidden="true" />
                                <AlertTitle>Tu ruta principal esta lista</AlertTitle>
                                <AlertDescription>
                                    Desde aqui puedes ir a tu cuenta, explorar negocios o continuar al panel de negocio cuando tengas uno activo.
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>

                    <section aria-labelledby="dashboard-actions-heading" className="flex flex-col gap-5">
                        <div className="flex flex-col gap-2">
                            <h3 id="dashboard-actions-heading" className="text-lg font-semibold text-foreground">
                                Acciones rapidas
                            </h3>
                            <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                Este acceso evita pantallas vacias despues de iniciar sesion y mantiene los destinos canonicos del producto.
                            </p>
                        </div>

                        <div className="grid gap-4 md:grid-cols-3">
                            {actionCards.map((action) => {
                                const Icon = action.icon;

                                return (
                                    <Card key={action.title} className="flex flex-col">
                                        <CardHeader>
                                            <div className="flex items-center gap-3">
                                                <span className="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                                    <Icon aria-hidden="true" />
                                                </span>
                                                <CardTitle className="text-lg">{action.title}</CardTitle>
                                            </div>
                                            <CardDescription>{action.description}</CardDescription>
                                        </CardHeader>
                                        <CardFooter className="mt-auto">
                                            <Button variant="outline" className="w-full" asChild data-testid={action.testId}>
                                                <Link href={action.href}>{action.button}</Link>
                                            </Button>
                                        </CardFooter>
                                    </Card>
                                );
                            })}

                            <Card className="flex flex-col">
                                <CardHeader>
                                    <div className="flex items-center gap-3">
                                        <span className="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground">
                                            <Store aria-hidden="true" />
                                        </span>
                                        <CardTitle className="text-lg">{panelText}</CardTitle>
                                    </div>
                                    <CardDescription>
                                        Entra al panel canonico de negocio o inicia el registro si todavia no tienes uno.
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <Separator />
                                </CardContent>
                                <CardFooter className="mt-auto">
                                    <Button className="w-full" asChild>
                                        <Link href={panelUrl}>
                                            {panelText}
                                            <ArrowRight data-icon="inline-end" aria-hidden="true" />
                                        </Link>
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>
                    </section>
                </div>
            </main>
        </AuthenticatedLayout>
    );
}
