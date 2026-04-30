import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

export default function Privacy({ version }) {
  return (
    <>
      <Head title="Política de Privacidad | Vito Business OS" />
      <main className="mx-auto max-w-3xl px-6 py-12 text-foreground">
        <p className="text-sm uppercase tracking-[0.2em] text-muted-foreground">Legal</p>
        <h1 className="mt-2 text-3xl font-semibold">Política de Privacidad</h1>
        <p className="mt-2 text-sm text-muted-foreground">Versión vigente: {version}</p>

        <section className="mt-8 flex flex-col gap-4 text-sm leading-7 text-muted-foreground">
          <p>
            En Vito Business OS tratamos datos para operar cuentas, publicar perfiles de negocio verificados,
            habilitar funcionalidades de directorio y mejorar la guía inteligente de recomendaciones.
          </p>
          <p>
            Solo mostramos en discovery negocios con verificación aprobada. La guía IA es no transaccional y no
            ejecuta compras, pagos ni cambios de estado sobre tu negocio.
          </p>
          <p>
            Conservamos evidencia de consentimiento (usuario, fecha, versión, IP y user-agent) para trazabilidad.
            También registramos eventos operativos para seguridad, auditoría y continuidad del servicio.
          </p>
          <p>
            Para ejercer derechos de acceso, corrección o eliminación, contáctanos usando los canales oficiales
            publicados por Vito Code.
          </p>
        </section>

        <div className="mt-10">
          <Button asChild variant="outline">
            <Link href={route('legal.terms')}>
              Ver Términos y Condiciones
            </Link>
          </Button>
        </div>
      </main>
    </>
  );
}
