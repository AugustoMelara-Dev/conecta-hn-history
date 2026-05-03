import { Head, useForm, usePage } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/Components/ui/alert-dialog';

/**
 * Guest Appointment Management Page
 *
 * Allows guests to view appointment details and cancel via signed URL.
 */
export default function ManageAppointment({ appointment, tenant }) {
    const { post, processing } = useForm();
    const { flash } = usePage().props;

    const handleCancel = () => {
        post(appointment.cancel_url);
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
            <Head title={`Gestionar cita - ${tenant.name}`} />

            <div className="min-h-screen bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-md overflow-hidden rounded-xl bg-white shadow-md">
                    <div className="bg-black px-6 py-4">
                        <div className="flex items-center space-x-3">
                            {tenant.logo_url && (
                                <img
                                    src={tenant.logo_url}
                                    alt={tenant.name}
                                    className="h-10 w-10 rounded-full bg-white object-cover"
                                />
                            )}
                            <div>
                                <p className="font-medium text-white">{tenant.name}</p>
                                <p className="text-sm text-gray-400">Gestión de cita</p>
                            </div>
                        </div>
                    </div>

                    <div className="p-6">
                        {flash?.success && (
                            <div className="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700" role="status">
                                <div className="flex items-center">
                                    <svg className="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clipRule="evenodd" />
                                    </svg>
                                    {flash.success}
                                </div>
                            </div>
                        )}

                        {flash?.error && (
                            <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-700" role="alert">
                                <div className="flex items-center">
                                    <svg className="mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                                        <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clipRule="evenodd" />
                                    </svg>
                                    {flash.error}
                                </div>
                            </div>
                        )}

                        <div className="mb-6 flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-gray-900">Detalles de tu cita</h2>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusStyles[appointment.status] || 'bg-gray-100 text-gray-800'}`}>
                                {statusLabels[appointment.status] || appointment.status}
                            </span>
                        </div>

                        <div className="space-y-4">
                            <div className="rounded-lg bg-gray-50 p-4">
                                <dl className="space-y-3">
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-sm text-gray-500">Cliente</dt>
                                        <dd className="text-right text-sm font-medium text-gray-900">{appointment.customer_name}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-sm text-gray-500">Servicio</dt>
                                        <dd className="text-right text-sm font-medium text-gray-900">{appointment.service_name}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-sm text-gray-500">Profesional</dt>
                                        <dd className="text-right text-sm font-medium text-gray-900">{appointment.provider_name}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4 border-t border-gray-200 pt-3">
                                        <dt className="text-sm text-gray-500">Fecha</dt>
                                        <dd className="text-right text-sm font-medium text-gray-900">{appointment.scheduled_at}</dd>
                                    </div>
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-sm text-gray-500">Hora</dt>
                                        <dd className="text-right text-sm font-medium text-gray-900">{appointment.time}</dd>
                                    </div>
                                </dl>
                            </div>
                        </div>

                        {appointment.can_cancel && (
                            <div className="mt-6 border-t border-gray-200 pt-6">
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <button
                                            type="button"
                                            disabled={processing}
                                            className="flex w-full items-center justify-center rounded-lg border border-transparent bg-red-600 px-4 py-3 text-sm font-medium text-white shadow-sm transition-colors hover:bg-red-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            {processing ? (
                                                <>
                                                    <svg className="-ml-1 mr-2 h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                                        <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"></circle>
                                                        <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                                    </svg>
                                                    Procesando...
                                                </>
                                            ) : (
                                                'Cancelar cita'
                                            )}
                                        </button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>Cancelar cita</AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Esta acción notificará al negocio y liberará el espacio reservado. No podrás revertirlo desde esta página.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel disabled={processing}>Mantener cita</AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={handleCancel}
                                                disabled={processing}
                                                className="bg-red-600 text-white hover:bg-red-700 focus-visible:ring-red-500"
                                            >
                                                Confirmar cancelación
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                                <p className="mt-2 text-center text-xs text-gray-500">
                                    Esta acción no se puede deshacer.
                                </p>
                            </div>
                        )}

                        {!appointment.can_cancel && (
                            <div className="mt-6 border-t border-gray-200 pt-6">
                                <p className="text-center text-sm text-gray-500">
                                    Esta cita ya no puede ser cancelada.
                                </p>
                            </div>
                        )}

                        <div className="mt-6 text-center">
                            <a
                                href={tenant.microsite_url}
                                className="text-sm text-gray-500 underline hover:text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2"
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
