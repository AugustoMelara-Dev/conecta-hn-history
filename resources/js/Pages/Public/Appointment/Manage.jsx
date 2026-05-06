import { Head, useForm, usePage } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

/**
 * Guest Appointment Management Page
 * 
 * Allows guests to view appointment details and cancel via signed URL.
 */
export default function ManageAppointment({ appointment, tenant }) {
    const { post, processing } = useForm();
    const { flash } = usePage().props;

    const handleCancel = (e) => {
        e.preventDefault();
        if (confirm('¿Estás seguro de que deseas cancelar esta cita?')) {
            post(window.location.href.replace('/manage', '/cancel'));
        }
    };

    const statusStyles = {
        pending: 'bg-yellow-100 text-yellow-800',
        confirmed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
        completed: 'bg-blue-100 text-blue-800',
    };

    const statusLabels = {
        pending: 'Pendiente',
        confirmed: 'Confirmada',
        cancelled: 'Cancelada',
        completed: 'Completada',
    };

    return (
        <PublicLayout>
            <Head title={`Gestionar Cita - ${tenant.name}`} />
            
            <div className="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
                <div className="max-w-md mx-auto bg-white rounded-xl shadow-md overflow-hidden">
                    {/* Header */}
                    <div className="bg-black px-6 py-4">
                        <div className="flex items-center space-x-3">
                            {tenant.logo_url && (
                                <img 
                                    src={tenant.logo_url} 
                                    alt={tenant.name}
                                    className="w-10 h-10 rounded-full object-cover bg-white"
                                />
                            )}
                            <div>
                                <p className="text-white font-medium">{tenant.name}</p>
                                <p className="text-gray-400 text-sm">Gestión de Cita</p>
                            </div>
                        </div>
                    </div>

                    <div className="p-6">
                        {/* Flash Messages */}
                        {flash?.success && (
                            <div className="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                                <div className="flex items-center">
                                    <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                    {flash.success}
                                </div>
                            </div>
                        )}

                        {flash?.error && (
                            <div className="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                                <div className="flex items-center">
                                    <svg className="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                    </svg>
                                    {flash.error}
                                </div>
                            </div>
                        )}

                        {/* Status Badge */}
                        <div className="flex justify-between items-center mb-6">
                            <h2 className="text-lg font-semibold text-gray-900">Detalles de tu Cita</h2>
                            <span className={`px-3 py-1 text-xs font-semibold rounded-full ${statusStyles[appointment.status] || 'bg-gray-100 text-gray-800'}`}>
                                {statusLabels[appointment.status] || appointment.status}
                            </span>
                        </div>

                        {/* Appointment Details */}
                        <div className="space-y-4">
                            <div className="bg-gray-50 rounded-lg p-4">
                                <dl className="space-y-3">
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Cliente</dt>
                                        <dd className="text-sm font-medium text-gray-900">{appointment.customer_name}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Servicio</dt>
                                        <dd className="text-sm font-medium text-gray-900">{appointment.service_name}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Profesional</dt>
                                        <dd className="text-sm font-medium text-gray-900">{appointment.provider_name}</dd>
                                    </div>
                                    <div className="border-t border-gray-200 pt-3 flex justify-between">
                                        <dt className="text-sm text-gray-500">Fecha</dt>
                                        <dd className="text-sm font-medium text-gray-900">{appointment.scheduled_at}</dd>
                                    </div>
                                    <div className="flex justify-between">
                                        <dt className="text-sm text-gray-500">Hora</dt>
                                        <dd className="text-sm font-medium text-gray-900">{appointment.time}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        {/* Cancel Button */}
                        {appointment.can_cancel && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <button
                                    onClick={handleCancel}
                                    disabled={processing}
                                    className="w-full flex justify-center items-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                >
                                    {processing ? (
                                        <>
                                            <svg className="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            Procesando...
                                        </>
                                    ) : (
                                        'Cancelar Cita'
                                    )}
                                </button>
                                <p className="mt-2 text-center text-xs text-gray-500">
                                    Esta acción no se puede deshacer.
                                </p>
                            </div>
                        )}

                        {/* Cannot Cancel Message */}
                        {!appointment.can_cancel && (
                            <div className="mt-6 pt-6 border-t border-gray-200">
                                <p className="text-center text-sm text-gray-500">
                                    Esta cita ya no puede ser cancelada.
                                </p>
                            </div>
                        )}

                        {/* Back Link */}
                        <div className="mt-6 text-center">
                            <a 
                                href={`/${tenant.slug}`}
                                className="text-sm text-gray-500 hover:text-gray-700 underline"
                            >
                                Volver a {tenant.name}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}
