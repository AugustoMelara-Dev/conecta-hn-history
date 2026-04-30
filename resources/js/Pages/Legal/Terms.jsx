import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';

export default function Terms({ version }) {
  return (
    <>
      <Head title="Términos y Condiciones | Vito Business OS" />
      <main className="mx-auto max-w-3xl px-6 py-12 text-foreground">
        <p className="text-sm uppercase tracking-[0.2em] text-muted-foreground">Legal</p>
        <h1 className="mt-2 text-3xl font-semibold">Términos y Condiciones</h1>
        <p className="mt-2 text-sm text-muted-foreground">Versión vigente: {version}</p>

        <section className="mt-8 flex flex-col gap-4 text-sm leading-7 text-muted-foreground">
          <p>
            Vito Business OS es una plataforma SaaS para gestión de negocio digital, panel operativo y publicación
            controlada en directorio. El registro es abierto, pero la publicación pública requiere claim verificado.
          </p>
          <p>
            El titular de la cuenta es responsable por la veracidad de la información del negocio, el uso de marcas
            y la evidencia enviada durante verificación.
          </p>
          <p>
            Ante reportes de suplantación, Vito Code puede pausar publicación de un perfil hasta completar revisión.
            Las decisiones de verificación quedan auditadas con actor, fecha y resultado.
          </p>
          <p>
            La guía IA funciona como recomendador de lectura. No realiza cobros ni transacciones automáticas en esta
            versión del producto.
          </p>
        </section>

        <div className="mt-10">
          <Button asChild variant="outline">
            <Link href={route('legal.privacy')}>
              Ver Política de Privacidad
            </Link>
          </Button>
        </div>
      </main>
    </>
  );
}
