import { useState, useEffect } from 'react';
import { Head, useForm } from '@inertiajs/react';
import axios from 'axios';
import PublicLayout from '@/Layouts/PublicLayout';

/**
 * Booking Wizard - Customer Appointment Booking Flow
 * 
 * 4-Step wizard:
 * 1. Select Service
 * 2. Select Provider  
 * 3. Select Date & Time
 * 4. Enter Contact Info & Confirm
 */
export default function BookingWizard({ tenant, services, providers }) {
    const [step, setStep] = useState(1);
    const [selectedService, setSelectedService] = useState(null);
    const [selectedProvider, setSelectedProvider] = useState(null);
    const [selectedDate, setSelectedDate] = useState('');
    const [availableSlots, setAvailableSlots] = useState([]);
    const [loadingSlots, setLoadingSlots] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        service_id: null,
        service_provider_id: null,
        date: '',
        time: '',
        customer_name: '',
        customer_email: '',
        customer_phone: '',
        customer_notes: '',
    });

    // Fetch slots when date or provider changes
    useEffect(() => {
        if (selectedDate && data.service_id) {
            fetchSlots();
        }
    }, [selectedDate, data.service_provider_id]);

    const fetchSlots = async () => {
        setLoadingSlots(true);
        try {
            const response = await axios.get(`/${tenant.slug}/book/slots`, {
                params: {
                    date: selectedDate,
                    service_id: data.service_id,
                    provider_id: data.service_provider_id,
                },
            });
            setAvailableSlots(response.data.slots);
        } catch (error) {
            console.error('Error fetching slots:', error);
            setAvailableSlots([]);
        }
        setLoadingSlots(false);
    };

    const handleServiceSelect = (service) => {
        setSelectedService(service);
        setData('service_id', service.id);
        setStep(2);
    };

    const handleProviderSelect = (provider) => {
        setSelectedProvider(provider);
        setData('service_provider_id', provider.id);
        setStep(3);
    };

    const handleDateChange = (e) => {
        const date = e.target.value;
        setSelectedDate(date);
        setData('date', date);
    };

    const handleTimeSelect = (slot) => {
        setData('time', slot.time);
        setStep(4);
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(`/${tenant.slug}/book`);
    };

    const goBack = () => {
        if (step > 1) setStep(step - 1);
    };

    // Get today's date for min date attribute
    const today = new Date().toISOString().split('T')[0];

    return (
        <PublicLayout>
            <Head title={`Reservar en ${tenant.name}`} />
            
            <div className="min-h-screen bg-gray-50 py-8">
                <div className="max-w-lg mx-auto px-4">
                    {/* Header */}
                    <div className="text-center mb-8">
                        <h1 className="text-2xl font-bold text-gray-900">
                            Reserva tu cita
                        </h1>
                        <p className="text-gray-600 mt-1">{tenant.name}</p>
                    </div>

                    {/* Progress Steps */}
                    <div className="flex justify-between mb-8">
                        {[1, 2, 3, 4].map((s) => (
                            <div
                                key={s}
                                className={`flex-1 h-2 mx-1 rounded-full ${
                                    s <= step ? 'bg-black' : 'bg-gray-200'
                                }`}
                            />
                        ))}
                    </div>

                    {/* Step 1: Select Service */}
                    {step === 1 && (
                        <div className="space-y-4">
                            <h2 className="text-lg font-semibold">Selecciona un servicio</h2>
                            {services.map((service) => (
                                <button
                                    key={service.id}
                                    onClick={() => handleServiceSelect(service)}
                                    className="w-full p-4 bg-white border border-gray-200 rounded-lg text-left hover:border-black transition-colors"
                                >
                                    <div className="flex justify-between items-start">
                                        <div>
                                            <h3 className="font-medium text-gray-900">{service.name}</h3>
                                            {service.description && (
                                                <p className="text-sm text-gray-500 mt-1">{service.description}</p>
                                            )}
                                            <p className="text-sm text-gray-400 mt-1">
                                                {service.duration_formatted}
                                            </p>
                                        </div>
                                        <span className="font-semibold text-gray-900">{service.price}</span>
                                    </div>
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Step 2: Select Provider */}
                    {step === 2 && (
                        <div className="space-y-4">
                            <button onClick={goBack} className="text-gray-500 hover:text-black mb-4">
                                ← Volver
                            </button>
                            <h2 className="text-lg font-semibold">¿Con quién prefieres?</h2>
                            
                            <button
                                onClick={() => {
                                    setSelectedProvider(null);
                                    setData('service_provider_id', providers[0]?.id);
                                    setStep(3);
                                }}
                                className="w-full p-4 bg-gray-100 border border-gray-200 rounded-lg text-left hover:border-black transition-colors"
                            >
                                <span className="font-medium">Sin preferencia</span>
                            </button>

                            {providers.map((provider) => (
                                <button
                                    key={provider.id}
                                    onClick={() => handleProviderSelect(provider)}
                                    className="w-full p-4 bg-white border border-gray-200 rounded-lg text-left hover:border-black transition-colors"
                                >
                                    <span className="font-medium">{provider.name}</span>
                                </button>
                            ))}
                        </div>
                    )}

                    {/* Step 3: Select Date & Time */}
                    {step === 3 && (
                        <div className="space-y-4">
                            <button onClick={goBack} className="text-gray-500 hover:text-black mb-4">
                                ← Volver
                            </button>
                            <h2 className="text-lg font-semibold">Escoge fecha y hora</h2>
                            
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Fecha
                                </label>
                                <input
                                    type="date"
                                    min={today}
                                    value={selectedDate}
                                    onChange={handleDateChange}
                                    className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-black"
                                />
                            </div>

                            {selectedDate && (
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-2">
                                        Hora disponible
                                    </label>
                                    {loadingSlots ? (
                                        <div className="text-center py-8 text-gray-500">
                                            Cargando horarios...
                                        </div>
                                    ) : availableSlots.length === 0 ? (
                                        <div className="text-center py-8 text-gray-500">
                                            No hay horarios disponibles para esta fecha
                                        </div>
                                    ) : (
                                        <div className="grid grid-cols-3 gap-2">
                                            {availableSlots.map((slot) => (
                                                <button
                                                    key={slot.time}
                                                    onClick={() => handleTimeSelect(slot)}
                                                    className={`py-3 px-2 text-sm border rounded-lg transition-colors ${
                                                        data.time === slot.time
                                                            ? 'bg-black text-white border-black'
                                                            : 'bg-white border-gray-200 hover:border-black'
                                                    }`}
                                                >
                                                    {slot.display}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 4: Contact Info & Confirm */}
                    {step === 4 && (
                        <form onSubmit={handleSubmit} className="space-y-4">
                            <button type="button" onClick={goBack} className="text-gray-500 hover:text-black mb-4">
                                ← Volver
                            </button>
                            <h2 className="text-lg font-semibold">Completa tu reserva</h2>

                            {/* Summary */}
                            <div className="bg-gray-100 p-4 rounded-lg space-y-2">
                                <p><strong>Servicio:</strong> {selectedService?.name}</p>
                                <p><strong>Con:</strong> {selectedProvider?.name || 'Sin preferencia'}</p>
                                <p><strong>Fecha:</strong> {selectedDate}</p>
                                <p><strong>Hora:</strong> {data.time}</p>
                                <p><strong>Precio:</strong> {selectedService?.price}</p>
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tu nombre *
                                </label>
                                <input
                                    type="text"
                                    value={data.customer_name}
                                    onChange={(e) => setData('customer_name', e.target.value)}
                                    className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-black"
                                    placeholder="Juan Pérez"
                                    required
                                />
                                {errors.customer_name && (
                                    <p className="text-red-500 text-sm mt-1">{errors.customer_name}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Correo Electrónico *
                                </label>
                                <input
                                    type="email"
                                    value={data.customer_email}
                                    onChange={(e) => setData('customer_email', e.target.value)}
                                    className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-black"
                                    placeholder="juan@ejemplo.com"
                                    required
                                />
                                {errors.customer_email && (
                                    <p className="text-red-500 text-sm mt-1">{errors.customer_email}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Tu teléfono *
                                </label>
                                <input
                                    type="tel"
                                    value={data.customer_phone}
                                    onChange={(e) => setData('customer_phone', e.target.value)}
                                    className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-black"
                                    placeholder="9999-9999"
                                    required
                                />
                                {errors.customer_phone && (
                                    <p className="text-red-500 text-sm mt-1">{errors.customer_phone}</p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-2">
                                    Notas (opcional)
                                </label>
                                <textarea
                                    value={data.customer_notes}
                                    onChange={(e) => setData('customer_notes', e.target.value)}
                                    className="w-full px-4 py-3 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-black"
                                    rows={3}
                                    placeholder="Algún comentario especial..."
                                />
                            </div>

                            {errors.time && (
                                <div className="bg-red-50 text-red-600 p-4 rounded-lg">
                                    {errors.time}
                                </div>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full py-4 bg-black text-white font-medium rounded-lg hover:bg-gray-800 transition-colors disabled:opacity-50"
                            >
                                {processing ? 'Reservando...' : 'Confirmar Reserva'}
                            </button>
                        </form>
                    )}
                </div>
            </div>
        </PublicLayout>
    );
}
