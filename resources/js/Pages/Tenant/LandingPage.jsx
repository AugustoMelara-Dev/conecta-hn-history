import LegacyArchived from '@/Pages/Tenant/LegacyArchived';

export default function LandingPage({ tenant }) {
    return (
        <LegacyArchived
            title="Editor legacy archivado"
            message="La presencia publica del negocio ya no se gestiona desde un landing builder separado."
            reason="El micrositio y el perfil publico oficial ahora se administran desde el panel principal en /app para evitar flujos duplicados y promesas fuera de scope."
            primaryAction={{
                href: route('filament.app.tenant.profile', { tenant: tenant?.slug ?? '' }),
                label: 'Abrir perfil publico oficial',
            }}
            secondaryAction={{
                href: route('tenant.microsite.show', { tenant: tenant?.slug ?? '' }),
                label: 'Ver mi micrositio',
                external: true,
            }}
        />
    );
}
