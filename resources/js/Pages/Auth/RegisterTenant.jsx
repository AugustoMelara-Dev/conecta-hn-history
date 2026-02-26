/**
 * Pages/Auth/RegisterTenant.jsx - Business Registration Wizard
 *
 * FEATURES:
 * - Multi-step wizard (Business Info → Owner Info → Confirmation)
 * - Image upload with preview
 * - Form validation with error states
 * - Category/City selection
 * - Terms acceptance
 *
 * PROPS:
 * @param {Array} props.categories - Available categories
 * @param {Array} props.cities - Available cities
 */

import { useState, useCallback } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import PublicLayout from '@/Layouts/PublicLayout';

// ═══════════════════════════════════════════════════════════════════════════
// STEP INDICATOR
// ═══════════════════════════════════════════════════════════════════════════
function StepIndicator({ currentStep, totalSteps }) {
    const steps = [
        { id: 1, name: 'Tu Negocio', icon: '🏪' },
        { id: 2, name: 'Tu Cuenta', icon: '👤' },
        { id: 3, name: 'Confirmar', icon: '✅' },
    ];

    return (
        <div className="flex items-center justify-center mb-8">
            {steps.map((step, index) => (
                <div key={step.id} className="flex items-center">
                    <div className={`flex items-center justify-center w-12 h-12 rounded-full font-bold text-lg transition-all ${step.id < currentStep
                        ? 'bg-emerald-500 text-white'
                        : step.id === currentStep
                            ? 'bg-blue-600 text-white ring-4 ring-blue-100'
                            : 'bg-gray-200 text-gray-500'
                        }`}>
                        {step.id < currentStep ? (
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                            </svg>
                        ) : (
                            step.icon
                        )}
                    </div>
                    {index < steps.length - 1 && (
                        <div className={`w-16 h-1 mx-2 rounded ${step.id < currentStep ? 'bg-emerald-500' : 'bg-gray-200'
                            }`} />
                    )}
                </div>
            ))}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// IMAGE UPLOAD
// ═══════════════════════════════════════════════════════════════════════════
function ImageUpload({ label, name, value, onChange, error, accept = 'image/*' }) {
    const [preview, setPreview] = useState(null);

    const handleChange = useCallback((e) => {
        const file = e.target.files?.[0];
        if (file) {
            onChange(name, file);
            const reader = new FileReader();
            reader.onloadend = () => setPreview(reader.result);
            reader.readAsDataURL(file);
        }
    }, [name, onChange]);

    return (
        <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">{label}</label>
            <div className={`relative border-2 border-dashed rounded-2xl p-6 text-center transition-colors ${error ? 'border-red-300 bg-red-50' : 'border-gray-200 hover:border-blue-300'
                }`}>
                {preview ? (
                    <div className="relative">
                        <img
                            src={preview}
                            alt="Preview"
                            className="w-32 h-32 object-cover rounded-xl mx-auto"
                        />
                        <button
                            type="button"
                            onClick={() => { setPreview(null); onChange(name, null); }}
                            className="absolute top-0 right-1/2 translate-x-16 -translate-y-2 w-8 h-8 bg-red-500 text-white rounded-full flex items-center justify-center"
                        >
                            ×
                        </button>
                    </div>
                ) : (
                    <label className="cursor-pointer">
                        <div className="w-16 h-16 bg-gray-100 rounded-xl flex items-center justify-center mx-auto mb-3">
                            <svg className="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <span className="text-sm text-gray-500">Click para subir imagen</span>
                        <input
                            type="file"
                            accept={accept}
                            onChange={handleChange}
                            className="hidden"
                        />
                    </label>
                )}
            </div>
            {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// FORM INPUT
// ═══════════════════════════════════════════════════════════════════════════
function FormInput({ label, error, required, ...props }) {
    return (
        <div>
            <label className="block text-sm font-semibold text-gray-700 mb-2">
                {label} {required && <span className="text-red-500">*</span>}
            </label>
            <input
                {...props}
                className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 transition-all ${error ? 'border-red-300' : 'border-gray-200'
                    }`}
            />
            {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function RegisterTenant({ categories = [], cities = [] }) {
    const [step, setStep] = useState(1);
    const [stepErrors, setStepErrors] = useState({}); // UX: Inline error state instead of alert()

    const { data, setData, post, processing, errors } = useForm({
        // Business Info
        name: '',
        slug: '',
        category_id: '',
        city_id: '',
        description: '',
        phone: '',
        whatsapp_number: '',
        address: '',
        logo: null,
        // Owner Info
        owner_name: '',
        email: '',
        password: '',
        password_confirmation: '',
        // Confirmation
        terms_accepted: false,
    });

    // ═══════════════════════════════════════════════════════════════════════════
    // FIX #2: Atomic state update - prevents race condition
    // Principle: Atomicity - single transaction for related state changes
    // ═══════════════════════════════════════════════════════════════════════════
    const handleNameChange = (value) => {
        // Generate slug synchronously
        const slug = value
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')  // Remove diacritics
            .replace(/[^a-z0-9]+/g, '-')     // Replace non-alphanumeric with dash
            .replace(/(^-|-$)/g, '');        // Trim leading/trailing dashes

        // Single atomic update - prevents React batching issues
        setData(prevData => ({
            ...prevData,
            name: value,
            slug: slug,
        }));
    };

    // ═══════════════════════════════════════════════════════════════════════════
    // FIX #9: Step validation - Guard Clause pattern with inline errors
    // Principle: Fail Fast - prevent invalid state transitions
    // UX FIX: Replaced window.alert() with local state for non-blocking feedback
    // ═══════════════════════════════════════════════════════════════════════════
    const canProceedToStep2 = data.name?.trim() && data.category_id && data.city_id;
    const canProceedToStep3 = data.owner_name?.trim() && data.email?.trim() && data.password?.trim();

    const nextStep = () => {
        // Clear previous step errors
        setStepErrors({});

        // Guard clauses for step transitions with inline error feedback
        if (step === 1 && !canProceedToStep2) {
            setStepErrors({ step1: 'Por favor completa los campos obligatorios: Nombre, Categoría y Ciudad' });
            return;
        }
        if (step === 2 && !canProceedToStep3) {
            setStepErrors({ step2: 'Por favor completa todos los campos de tu cuenta' });
            return;
        }
        setStep(Math.min(step + 1, 3));
    };
    const prevStep = () => {
        setStepErrors({}); // Clear errors when going back
        setStep(Math.max(step - 1, 1));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('tenant.register.store'), {
            forceFormData: true,
        });
    };

    return (
        <PublicLayout>
            <Head>
                <title>Registra tu Negocio - ConectaHN</title>
                <meta name="description" content="Crea tu perfil de negocio gratis en ConectaHN y conecta con miles de clientes." />
            </Head>

            <div className="min-h-screen bg-gradient-to-b from-blue-50 to-white py-12 px-4">
                <div className="max-w-2xl mx-auto">
                    {/* Header */}
                    <div className="text-center mb-8">
                        <h1 className="text-3xl font-bold text-gray-900 mb-2">
                            Registra tu Negocio
                        </h1>
                        <p className="text-gray-500">
                            Crea tu perfil gratis y llega a más clientes
                        </p>
                    </div>

                    {/* Step Indicator */}
                    <StepIndicator currentStep={step} totalSteps={3} />

                    {/* Form Card */}
                    <div className="bg-white rounded-3xl shadow-xl border border-gray-100 p-8">
                        <form onSubmit={handleSubmit}>
                            {/* Step 1: Business Info */}
                            {step === 1 && (
                                <div className="space-y-6">
                                    <h2 className="text-xl font-bold text-gray-900 mb-4">
                                        🏪 Información del Negocio
                                    </h2>

                                    {/* Inline Error Display - UX Pattern Standardization */}
                                    {stepErrors.step1 && (
                                        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
                                            <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                            </svg>
                                            {stepErrors.step1}
                                        </div>
                                    )}

                                    <FormInput
                                        label="Nombre del Negocio"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => handleNameChange(e.target.value)}
                                        placeholder="Ej: Tacos El Patrón"
                                        error={errors.name}
                                        required
                                    />

                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-2">
                                                Categoría <span className="text-red-500">*</span>
                                            </label>
                                            <select
                                                value={data.category_id}
                                                onChange={(e) => setData('category_id', e.target.value)}
                                                className={`w-full px-4 py-3 bg-gray-50 border rounded-xl ${errors.category_id ? 'border-red-300' : 'border-gray-200'
                                                    }`}
                                            >
                                                <option value="">Seleccionar...</option>
                                                {categories.map((cat) => (
                                                    <option key={cat.id} value={cat.id}>{cat.name}</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block text-sm font-semibold text-gray-700 mb-2">
                                                Ciudad <span className="text-red-500">*</span>
                                            </label>
                                            <select
                                                value={data.city_id}
                                                onChange={(e) => setData('city_id', e.target.value)}
                                                className={`w-full px-4 py-3 bg-gray-50 border rounded-xl ${errors.city_id ? 'border-red-300' : 'border-gray-200'
                                                    }`}
                                            >
                                                <option value="">Seleccionar...</option>
                                                {cities.map((city) => (
                                                    <option key={city.id} value={city.id}>{city.name}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-gray-700 mb-2">Descripción</label>
                                        <textarea
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Describe tu negocio en pocas palabras..."
                                            rows={3}
                                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                                        />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4">
                                        <FormInput
                                            label="Teléfono"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                            placeholder="+504 9999-9999"
                                            error={errors.phone}
                                        />
                                        <FormInput
                                            label="WhatsApp"
                                            type="tel"
                                            value={data.whatsapp_number}
                                            onChange={(e) => setData('whatsapp_number', e.target.value)}
                                            placeholder="+504 9999-9999"
                                            error={errors.whatsapp_number}
                                        />
                                    </div>

                                    <FormInput
                                        label="Dirección"
                                        type="text"
                                        value={data.address}
                                        onChange={(e) => setData('address', e.target.value)}
                                        placeholder="Colonia, Avenida, Calle..."
                                        error={errors.address}
                                    />

                                    <ImageUpload
                                        label="Logo del Negocio"
                                        name="logo"
                                        value={data.logo}
                                        onChange={setData}
                                        error={errors.logo}
                                    />

                                    <button
                                        type="button"
                                        onClick={nextStep}
                                        className="w-full py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors"
                                    >
                                        Continuar →
                                    </button>
                                </div>
                            )}

                            {/* Step 2: Owner Info */}
                            {step === 2 && (
                                <div className="space-y-6">
                                    <h2 className="text-xl font-bold text-gray-900 mb-4">
                                        👤 Tu Cuenta
                                    </h2>

                                    {/* Inline Error Display - UX Pattern Standardization */}
                                    {stepErrors.step2 && (
                                        <div className="p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm flex items-center gap-2">
                                            <svg className="w-5 h-5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                            </svg>
                                            {stepErrors.step2}
                                        </div>
                                    )}

                                    <FormInput
                                        label="Tu Nombre"
                                        type="text"
                                        value={data.owner_name}
                                        onChange={(e) => setData('owner_name', e.target.value)}
                                        placeholder="Juan Pérez"
                                        error={errors.owner_name}
                                        required
                                    />

                                    <FormInput
                                        label="Correo Electrónico"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="tu@email.com"
                                        error={errors.email}
                                        required
                                    />

                                    <FormInput
                                        label="Contraseña"
                                        type="password"
                                        value={data.password}
                                        onChange={(e) => setData('password', e.target.value)}
                                        placeholder="Mínimo 8 caracteres"
                                        error={errors.password}
                                        required
                                    />

                                    <FormInput
                                        label="Confirmar Contraseña"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={(e) => setData('password_confirmation', e.target.value)}
                                        placeholder="Repite tu contraseña"
                                        error={errors.password_confirmation}
                                        required
                                    />

                                    <div className="flex gap-4">
                                        <button
                                            type="button"
                                            onClick={prevStep}
                                            className="flex-1 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors"
                                        >
                                            ← Atrás
                                        </button>
                                        <button
                                            type="button"
                                            onClick={nextStep}
                                            className="flex-1 py-4 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition-colors"
                                        >
                                            Continuar →
                                        </button>
                                    </div>
                                </div>
                            )}

                            {/* Step 3: Confirmation */}
                            {step === 3 && (
                                <div className="space-y-6">
                                    <h2 className="text-xl font-bold text-gray-900 mb-4">
                                        ✅ Confirmar Registro
                                    </h2>

                                    {/* Summary */}
                                    <div className="bg-gray-50 rounded-2xl p-6 space-y-4">
                                        <div className="flex justify-between">
                                            <span className="text-gray-500">Negocio</span>
                                            <span className="font-semibold">{data.name}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-gray-500">URL</span>
                                            <span className="font-mono text-blue-600">{typeof window !== 'undefined' ? window.location.host : 'conectahn.com'}/{data.slug}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-gray-500">Email</span>
                                            <span className="font-medium">{data.email}</span>
                                        </div>
                                    </div>

                                    {/* Terms */}
                                    <label className="flex items-start gap-3 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            checked={data.terms_accepted}
                                            onChange={(e) => setData('terms_accepted', e.target.checked)}
                                            className="mt-1 w-5 h-5 text-blue-600 rounded focus:ring-blue-500"
                                        />
                                        <span className="text-sm text-gray-600">
                                            Acepto los{' '}
                                            <a href="/terms" className="text-blue-600 hover:underline">Términos de Servicio</a>
                                            {' '}y la{' '}
                                            <a href="/privacy" className="text-blue-600 hover:underline">Política de Privacidad</a>
                                        </span>
                                    </label>
                                    {errors.terms_accepted && (
                                        <p className="text-sm text-red-500">{errors.terms_accepted}</p>
                                    )}

                                    <div className="flex gap-4">
                                        <button
                                            type="button"
                                            onClick={prevStep}
                                            className="flex-1 py-4 bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold rounded-xl transition-colors"
                                        >
                                            ← Atrás
                                        </button>
                                        <button
                                            type="submit"
                                            disabled={processing || !data.terms_accepted}
                                            className="flex-1 py-4 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                                        >
                                            {processing ? 'Registrando...' : '🚀 Crear mi Negocio'}
                                        </button>
                                    </div>
                                </div>
                            )}
                        </form>
                    </div>

                    {/* Login Link */}
                    <p className="text-center text-gray-500 mt-6">
                        ¿Ya tienes cuenta?{' '}
                        <Link href={route('login')} className="text-blue-600 hover:underline font-medium">
                            Inicia Sesión
                        </Link>
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
