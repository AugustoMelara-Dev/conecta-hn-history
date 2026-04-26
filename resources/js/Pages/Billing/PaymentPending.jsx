import { Head } from '@inertiajs/react';
import GuestLayout from '@/Layouts/GuestLayout';
import SupportDiagnosticCard from '@/Components/SupportDiagnosticCard';

/**
 * PaymentPending - Payment Instructions Page
 * 
 * MIGRATED FROM: App\Livewire\Pages\PaymentPending
 * 
 * Shows bank transfer instructions and WhatsApp support link.
 */
export default function PaymentPending({ payment, bankInstructions, whatsappUrl, diagnostic = null }) {
    const hasWhatsAppSupport = typeof whatsappUrl === 'string' && whatsappUrl.startsWith('https://wa.me/');

    return (
        <GuestLayout>
            <Head title="Completar Pago | Conecta HN" />

            <div className="min-h-screen bg-gradient-to-br from-blue-50 to-indigo-100 py-12 px-4">
                <div className="max-w-lg mx-auto">
                    {/* Success Card */}
                    <div className="bg-white rounded-2xl shadow-xl p-8 text-center">
                        <div className="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <span className="text-4xl">⏳</span>
                        </div>

                        <h1 className="text-2xl font-bold text-gray-900 mb-2">
                            ¡Falta un paso!
                        </h1>
                        <p className="text-gray-600 mb-6">
                            Tu negocio <strong>{payment.tenant_name}</strong> está casi listo.
                            Solo necesitamos confirmar tu pago PRO.
                        </p>

                        {/* Payment Details */}
                        <div className="bg-gray-50 rounded-xl p-6 mb-6">
                            <div className="flex justify-between items-center mb-4">
                                <span className="text-gray-500">Referencia:</span>
                                <span className="font-mono font-bold text-blue-600">{payment.reference_number}</span>
                            </div>
                            <div className="flex justify-between items-center mb-4">
                                <span className="text-gray-500">Monto:</span>
                                <span className="text-2xl font-bold text-gray-900">
                                    L. {payment.amount?.toLocaleString('es-HN', { minimumFractionDigits: 2 })}
                                </span>
                            </div>
                            <div className="flex justify-between items-center">
                                <span className="text-gray-500">Estado:</span>
                                <span className="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                                    Pendiente
                                </span>
                            </div>
                        </div>

                        {/* Bank Instructions */}
                        {bankInstructions && (
                            <div className="bg-blue-50 border border-blue-200 rounded-xl p-4 mb-6 text-left">
                                <h3 className="font-semibold text-blue-900 mb-2">Instrucciones de Pago:</h3>
                                <div
                                    className="text-sm text-blue-800 prose prose-sm"
                                    dangerouslySetInnerHTML={{ __html: bankInstructions }}
                                />
                            </div>
                        )}

                        {/* WhatsApp CTA */}
                        {hasWhatsAppSupport ? (
                            <a
                                href={whatsappUrl}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="w-full inline-flex items-center justify-center gap-2 py-4 px-6 bg-green-500 hover:bg-green-600 text-white font-bold rounded-xl transition-colors"
                            >
                                <svg className="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" />
                                </svg>
                                Enviar Comprobante por WhatsApp
                            </a>
                        ) : (
                            <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                El numero de soporte por WhatsApp no esta configurado. Envia tu comprobante a soporte@conectahn.com con la referencia {payment.reference_number}.
                            </div>
                        )}

                        <p className="text-sm text-gray-500 mt-4">
                            Una vez confirmado tu pago, recibirás un correo de activación.
                        </p>
                        <SupportDiagnosticCard diagnostic={diagnostic} className="mt-6" />
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
