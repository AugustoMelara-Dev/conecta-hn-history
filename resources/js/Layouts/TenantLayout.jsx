import { Head } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export default function TenantLayout({ tenant, children }) {
    // Extract theme config with fallbacks
    const theme = tenant?.theme_config || {
        primary_color: '#000000',
        radius: '0.5rem',
        font: 'Inter',
    };

    return (
        <div
            className="min-h-screen bg-background"
            style={{
                '--primary': theme.primary_color,
                '--radius': theme.radius,
                fontFamily: theme.font,
            }}
        >
            {/* Tenant Header */}
            <header className="border-b bg-card">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        {/* Logo & Name */}
                        <div className="flex items-center gap-3">
                            {tenant?.logo_path ? (
                                <img
                                    src={`/storage/${tenant.logo_path}`}
                                    alt={tenant.name}
                                    className="w-10 h-10 rounded-lg object-cover"
                                />
                            ) : (
                                <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                                    <span className="font-bold text-sm">
                                        {tenant?.name?.substring(0, 2).toUpperCase()}
                                    </span>
                                </div>
                            )}
                            <div>
                                <h1 className="font-bold text-lg">{tenant?.name}</h1>
                                {tenant?.category?.name && (
                                    <p className="text-xs text-muted-foreground">
                                        {tenant.category.name}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* WhatsApp Contact (if available) */}
                        {tenant?.whatsapp_number && (
                            <a
                                href={`https://wa.me/${tenant.whatsapp_number}`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                            >
                                <svg className="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z" />
                                </svg>
                                Contactar
                            </a>
                        )}
                    </div>
                </div>
            </header>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                {children}
            </main>

            {/* Tenant Footer */}
            <footer className="border-t bg-muted/30 mt-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                    <div className="text-center text-sm text-muted-foreground">
                        <p>{tenant?.name} - Parte de Conecta HN</p>
                        {tenant?.address && <p className="mt-1">{tenant.address}</p>}
                    </div>
                </div>
            </footer>
        </div>
    );
}
