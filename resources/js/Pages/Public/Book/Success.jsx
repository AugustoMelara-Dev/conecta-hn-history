import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

/**
 * Booking Success Page - Confirmation after appointment creation
 */
export default function BookingSuccess({ tenant, appointment }) {
    return (
        <PublicLayout>
            <Head title={`Reserva Confirmada - ${tenant.name}`} />
            
            <div className="min-h-screen bg-gray-50 flex items-center justify-center py-12 px-4">
                <div className="max-w-md w-full bg-white rounded-2xl shadow-lg p-8 text-center">
                    {/* Success Icon */}
                    <div className="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg 
                            className="w-10 h-10 text-green-600" 
                            fill="none" 
                            stroke="currentColor" 
                            viewBox="0 0 24 24"
                        >
                            <path 
                                strokeLinecap="round" 
                                strokeLinejoin="round" 
                                strokeWidth={2} 
                                d="M5 13l4 4L19 7" 
                            />
                        </svg>
                    </div>

                    <h1 className="text-2xl font-bold text-gray-900 mb-2">
                        ¡Reserva Confirmada!
                    </h1>
                    
                    <p className="text-gray-600 mb-6">
                        Hemos recibido tu reserva. Te contactaremos pronto para confirmar.
                    </p>

                    {appointment && (
                        <div className="bg-gray-50 rounded-lg p-4 mb-6 text-left">
                            <h3 className="font-medium text-gray-900 mb-3">Detalles de tu cita:</h3>
                            <dl className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Servicio:</dt>
                                    <dd className="font-medium">{appointment.service_name}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Con:</dt>
                                    <dd className="font-medium">{appointment.provider_name}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Fecha:</dt>
                                    <dd className="font-medium">{appointment.scheduled_at}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-gray-500">Hora:</dt>
                                    <dd className="font-medium">{appointment.time}</dd>
                                </div>
                            </dl>
                        </div>
                    )}

                    <div className="space-y-3">
                        <Link
                            href={`/${tenant.slug}`}
                            className="block w-full py-3 bg-black text-white font-medium rounded-lg hover:bg-gray-800 transition-colors"
                        >
                            Volver al Negocio
                        </Link>
                        
                        <Link
                            href={`/${tenant.slug}/book`}
                            className="block w-full py-3 bg-gray-100 text-gray-700 font-medium rounded-lg hover:bg-gray-200 transition-colors"
                        >
                            Hacer Otra Reserva
                        </Link>
                    </div>

                    <p className="text-xs text-gray-400 mt-6">
                        Reserva en {tenant.name}
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
