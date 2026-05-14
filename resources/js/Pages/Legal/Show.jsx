import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, FileText, ShieldCheck } from 'lucide-react';

import PublicLayout from '@/Layouts/PublicLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';

const documents = {
    terms: {
        title: 'Términos de servicio',
        eyebrow: 'Condiciones de uso',
        description:
            'Reglas básicas para usar Vito Business OS como visitante, usuario o negocio registrado.',
        icon: FileText,
        sections: [
            {
                title: 'Uso de la plataforma',
                body:
                    'La plataforma permite publicar negocios, consultar el directorio y operar herramientas comerciales disponibles según el plan y las capacidades activas del tenant.',
            },
            {
                title: 'Cuentas de negocio',
                body:
                    'La persona que registra un negocio declara tener autorización para administrarlo y mantener actualizada la información pública, comercial y de contacto.',
            },
            {
                title: 'Planes y activación',
                body:
                    'Las funciones pagas no se consideran activas hasta que el pago correspondiente sea revisado o aprobado por el flujo vigente del sistema.',
            },
            {
                title: 'Contenido publicado',
                body:
                    'Cada negocio es responsable de sus productos, servicios, precios, disponibilidad, imágenes y mensajes publicados en su micrositio.',
            },
        ],
    },
    privacy: {
        title: 'Política de privacidad',
        eyebrow: 'Datos y seguridad',
        description:
            'Resumen claro de cómo se tratan los datos necesarios para operar cuentas, directorio, micrositios y paneles.',
        icon: ShieldCheck,
        sections: [
            {
                title: 'Datos que se solicitan',
                body:
                    'Se solicitan datos de cuenta, negocio, ubicación comercial, contacto y operación necesarios para publicar el negocio y habilitar el panel correspondiente.',
            },
            {
                title: 'Datos públicos y privados',
                body:
                    'El directorio y los micrositios usan solo información pública del negocio. La información operativa privada debe mantenerse aislada por tenant.',
            },
            {
                title: 'Seguridad de acceso',
                body:
                    'Las sesiones, permisos y rutas del panel se validan desde el backend. El acceso privado depende del usuario autenticado y su relación con el tenant.',
            },
            {
                title: 'Solicitudes y correcciones',
                body:
                    'Los negocios pueden corregir información desde su panel o solicitar apoyo cuando una actualización requiera revisión administrativa.',
            },
        ],
    },
};

export default function LegalShow({ kind = 'terms' }) {
    const document = documents[kind] || documents.terms;
    const Icon = document.icon;

    return (
        <PublicLayout>
            <Head title={`${document.title} | Vito Business OS`} />

            <section className="border-b bg-muted/30">
                <div className="mx-auto flex max-w-5xl flex-col gap-6 px-4 py-12 sm:px-6 lg:px-8">
                    <Button variant="ghost" className="w-fit gap-2 px-0" asChild>
                        <Link href="/registrar-negocio">
                            <ArrowLeft aria-hidden="true" />
                            Volver al registro
                        </Link>
                    </Button>

                    <div className="flex flex-col gap-4">
                        <Badge variant="secondary" className="w-fit gap-2">
                            <Icon aria-hidden="true" />
                            {document.eyebrow}
                        </Badge>
                        <div className="max-w-3xl">
                            <h1 className="font-display text-4xl font-bold tracking-normal text-foreground sm:text-5xl">
                                {document.title}
                            </h1>
                            <p className="mt-4 text-base leading-7 text-muted-foreground sm:text-lg">
                                {document.description}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <section className="mx-auto grid max-w-5xl gap-4 px-4 py-10 sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle>Resumen vigente</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-6">
                        {document.sections.map((section, index) => (
                            <div key={section.title} className="grid gap-4">
                                {index > 0 ? <Separator /> : null}
                                <div>
                                    <h2 className="text-lg font-semibold text-foreground">{section.title}</h2>
                                    <p className="mt-2 text-sm leading-7 text-muted-foreground">{section.body}</p>
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </section>
        </PublicLayout>
    );
}
