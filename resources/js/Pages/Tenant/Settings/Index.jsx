/**
 * Tenant/Settings/Index.jsx - Unified Settings Hub
 *
 * FEATURES:
 * - Tabbed Interface (Profile, Schedule, Security)
 * - Isolated useForm hooks for each section
 * - Logo/Cover upload with preview
 * - Schedule matrix with day toggles
 *
 * PROPS (from SettingsController@index):
 * @param {Object} props.tenant - All tenant data
 * @param {Object} props.schedule - { monday: { isOpen, open, close }, ... }
 * @param {Object} props.dayLabels - { monday: 'Lunes', ... }
 */

import { useState, useEffect } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';

const fieldErrorId = (field) => `${field}-error`;
const fieldHelpId = (field) => `${field}-help`;

function describedBy(field, errors, helpId = null) {
    return errors?.[field] ? fieldErrorId(field) : helpId;
}

// ═══════════════════════════════════════════════════════════════════════════
// TAB CONFIGURATION
// ═══════════════════════════════════════════════════════════════════════════
const TABS = [
    { id: 'profile', label: '📝 Perfil', icon: 'profile' },
    { id: 'schedule', label: '🕐 Horarios', icon: 'schedule' },
    { id: 'security', label: '🔒 Seguridad', icon: 'security' },
];

// ═══════════════════════════════════════════════════════════════════════════
// PROFILE TAB (Isolated Form 1)
// ═══════════════════════════════════════════════════════════════════════════
function ProfileTab({ tenant }) {
    const { data, setData, post, processing, errors, progress } = useForm({
        name: tenant?.name || '',
        description: tenant?.description || '',
        about: tenant?.about || '',
        phone: tenant?.phone || '',
        whatsapp_number: tenant?.whatsapp_number || '',
        address: tenant?.address || '',
        instagram_url: tenant?.instagram_url || '',
        facebook_url: tenant?.facebook_url || '',
        tiktok_url: tenant?.tiktok_url || '',
        website_url: tenant?.website_url || '',
        logo: null,
        cover: null,
        _method: 'PUT',
    });

    const [logoPreview, setLogoPreview] = useState(tenant?.logo_url || null);
    const [coverPreview, setCoverPreview] = useState(tenant?.cover_url || null);

    // ═══════════════════════════════════════════════════════════════════════════
    // MEMORY MANAGEMENT: Revoke Blob URLs on cleanup or change
    // Prevents browser memory leaks from accumulated object URLs
    // ═══════════════════════════════════════════════════════════════════════════
    useEffect(() => {
        return () => {
            if (logoPreview && logoPreview.startsWith('blob:')) {
                URL.revokeObjectURL(logoPreview);
            }
        };
    }, [logoPreview]);

    useEffect(() => {
        return () => {
            if (coverPreview && coverPreview.startsWith('blob:')) {
                URL.revokeObjectURL(coverPreview);
            }
        };
    }, [coverPreview]);

    const handleLogoChange = (e) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('logo', file);
            // Revoke previous URL before creating new one
            if (logoPreview && logoPreview.startsWith('blob:')) {
                URL.revokeObjectURL(logoPreview);
            }
            setLogoPreview(URL.createObjectURL(file));
        }
    };

    const handleCoverChange = (e) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('cover', file);
            // Revoke previous URL before creating new one
            if (coverPreview && coverPreview.startsWith('blob:')) {
                URL.revokeObjectURL(coverPreview);
            }
            setCoverPreview(URL.createObjectURL(file));
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post(route('tenant.settings.update-profile'), { forceFormData: true });
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Visual Branding Card */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                {/* Cover Image */}
                <div className="relative h-40 bg-gradient-to-r from-blue-500 to-indigo-600">
                    {coverPreview && (
                        <img src={coverPreview} alt="Cover" className="w-full h-full object-cover" />
                    )}
                    <label htmlFor="cover-upload" className="absolute bottom-3 right-3 px-3 py-1.5 bg-white/90 backdrop-blur text-gray-700 text-sm font-medium rounded-lg cursor-pointer hover:bg-white transition-colors">
                        <input
                            id="cover-upload"
                            name="cover"
                            type="file"
                            accept="image/*"
                            onChange={handleCoverChange}
                            className="sr-only"
                            aria-label="Cambiar imagen de portada"
                        />
                        📷 Cambiar Portada
                    </label>
                </div>

                {/* Logo */}
                <div className="relative -mt-12 ml-6">
                    <div className="w-24 h-24 rounded-xl bg-white shadow-lg border-4 border-white overflow-hidden">
                        {logoPreview ? (
                            <img src={logoPreview} alt="Logo" className="w-full h-full object-cover" />
                        ) : (
                            <div className="w-full h-full bg-gradient-to-br from-blue-500 to-indigo-600 flex items-center justify-center text-white text-2xl font-bold">
                                {data.name?.substring(0, 2) || 'T'}
                            </div>
                        )}
                    </div>
                    <label htmlFor="logo-upload" className="absolute -bottom-1 -right-1 p-1.5 bg-blue-600 text-white rounded-full cursor-pointer hover:bg-blue-700">
                        <input
                            id="logo-upload"
                            name="logo"
                            type="file"
                            accept="image/*"
                            onChange={handleLogoChange}
                            className="sr-only"
                            aria-label="Cambiar logotipo"
                        />
                        <span className="sr-only">Cambiar logotipo</span>
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                    </label>
                </div>

                {/* Basic Info */}
                <div className="p-6 pt-4 space-y-4">
                    <div>
                        <label htmlFor="settings-name" className="block text-sm font-medium text-gray-700 mb-1">Nombre del Negocio *</label>
                        <input
                            id="settings-name"
                            name="name"
                            type="text"
                            autoComplete="organization"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            aria-invalid={Boolean(errors.name)}
                            aria-describedby={describedBy('name', errors)}
                            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 ${errors.name ? 'border-red-300' : 'border-gray-200'}`}
                        />
                        {errors.name && <p id={fieldErrorId('name')} role="alert" className="mt-1 text-sm text-red-500">{errors.name}</p>}
                    </div>

                    <div>
                        <label htmlFor="settings-description" className="block text-sm font-medium text-gray-700 mb-1">Descripción Corta</label>
                        <textarea
                            id="settings-description"
                            name="description"
                            rows={2}
                            value={data.description}
                            onChange={(e) => setData('description', e.target.value)}
                            placeholder="Una frase que define tu negocio..."
                            aria-label="Descripcion corta"
                            aria-invalid={Boolean(errors.description)}
                            aria-describedby={describedBy('description', errors)}
                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 resize-none"
                        />
                        {errors.description && <p id={fieldErrorId('description')} role="alert" className="mt-1 text-sm text-red-500">{errors.description}</p>}
                    </div>

                    <div>
                        <label htmlFor="settings-about" className="block text-sm font-medium text-gray-700 mb-1">Nuestra Historia</label>
                        <textarea
                            id="settings-about"
                            name="about"
                            rows={4}
                            value={data.about}
                            onChange={(e) => setData('about', e.target.value)}
                            placeholder="Cuéntale a tus clientes quiénes son..."
                            aria-label="Nuestra historia"
                            aria-invalid={Boolean(errors.about)}
                            aria-describedby={describedBy('about', errors)}
                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 resize-none"
                        />
                        {errors.about && <p id={fieldErrorId('about')} role="alert" className="mt-1 text-sm text-red-500">{errors.about}</p>}
                    </div>
                </div>
            </div>

            {/* Contact & Social */}
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 className="font-semibold text-gray-900 mb-4">📞 Contacto y Redes</h2>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <InputWithIcon id="settings-phone" name="phone" label="Teléfono" icon="📱" value={data.phone} onChange={(v) => setData('phone', v)} placeholder="+504 9999-9999" autoComplete="tel" inputMode="tel" error={errors.phone} />
                    <InputWithIcon id="settings-whatsapp" name="whatsapp_number" label="WhatsApp" icon="💬" value={data.whatsapp_number} onChange={(v) => setData('whatsapp_number', v)} placeholder="+504 9999-9999" autoComplete="tel" inputMode="tel" error={errors.whatsapp_number} />
                    <div className="md:col-span-2">
                        <InputWithIcon id="settings-address" name="address" label="Dirección" icon="📍" value={data.address} onChange={(v) => setData('address', v)} placeholder="Colonia Kennedy, Tegucigalpa" autoComplete="street-address" error={errors.address} />
                    </div>
                    <InputWithIcon id="settings-instagram" name="instagram_url" label="Instagram" icon="📸" value={data.instagram_url} onChange={(v) => setData('instagram_url', v)} type="url" inputMode="url" placeholder="https://instagram.com/tunegocio" error={errors.instagram_url} />
                    <InputWithIcon id="settings-facebook" name="facebook_url" label="Facebook" icon="👍" value={data.facebook_url} onChange={(v) => setData('facebook_url', v)} type="url" inputMode="url" placeholder="https://facebook.com/tunegocio" error={errors.facebook_url} />
                    <InputWithIcon id="settings-tiktok" name="tiktok_url" label="TikTok" icon="🎵" value={data.tiktok_url} onChange={(v) => setData('tiktok_url', v)} type="url" inputMode="url" placeholder="https://tiktok.com/@tunegocio" error={errors.tiktok_url} />
                    <InputWithIcon id="settings-website" name="website_url" label="Sitio Web" icon="🌐" value={data.website_url} onChange={(v) => setData('website_url', v)} type="url" inputMode="url" placeholder="https://tunegocio.com" error={errors.website_url} />
                </div>
            </div>

            {/* Submit Button */}
            <div className="flex justify-end">
                <button
                    type="submit"
                    disabled={processing}
                    className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50"
                >
                    {processing ? 'Guardando...' : 'Guardar Perfil'}
                </button>
            </div>
        </form>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// SCHEDULE TAB (Isolated Form 2)
// ═══════════════════════════════════════════════════════════════════════════
function ScheduleTab({ schedule: initialSchedule, dayLabels }) {
    const { data, setData, put, processing, errors } = useForm({
        schedule: initialSchedule || {},
    });

    const handleToggleDay = (day) => {
        setData('schedule', {
            ...data.schedule,
            [day]: { ...data.schedule[day], isOpen: !data.schedule[day]?.isOpen },
        });
    };

    const handleTimeChange = (day, field, value) => {
        setData('schedule', {
            ...data.schedule,
            [day]: { ...data.schedule[day], [field]: value },
        });
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('tenant.settings.update-schedule'));
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 className="font-semibold text-gray-900 mb-4">🕐 Horarios de Atención</h2>
                <div className="space-y-3">
                    {Object.entries(dayLabels).map(([day, label]) => (
                        <div
                            key={day}
                            className={`flex items-center gap-4 p-3 bg-gray-50 rounded-xl ${!data.schedule[day]?.isOpen ? 'opacity-50' : ''}`}
                        >
                            <div className="w-24 font-medium text-gray-900">{label}</div>

                            {/* Toggle */}
                            <button
                                type="button"
                                onClick={() => handleToggleDay(day)}
                                role="switch"
                                aria-checked={Boolean(data.schedule[day]?.isOpen)}
                                aria-label={`${label}: ${data.schedule[day]?.isOpen ? 'abierto' : 'cerrado'}`}
                                className={`relative w-12 h-7 rounded-full transition-colors ${data.schedule[day]?.isOpen ? 'bg-emerald-500' : 'bg-gray-300'
                                    }`}
                            >
                                <span aria-hidden="true" className={`absolute top-1 w-5 h-5 bg-white rounded-full shadow transition-all ${data.schedule[day]?.isOpen ? 'right-1' : 'left-1'
                                    }`} />
                            </button>

                            <span className={`text-sm ${data.schedule[day]?.isOpen ? 'text-emerald-600' : 'text-gray-500'}`}>
                                {data.schedule[day]?.isOpen ? 'Abierto' : 'Cerrado'}
                            </span>

                            {/* Time Inputs */}
                            <div className="flex items-center gap-2 ml-auto">
                                <input
                                    id={`schedule-${day}-open`}
                                    name={`schedule[${day}][open]`}
                                    type="time"
                                    value={data.schedule[day]?.open || ''}
                                    onChange={(e) => handleTimeChange(day, 'open', e.target.value)}
                                    disabled={!data.schedule[day]?.isOpen}
                                    aria-label={`Hora de apertura de ${label}`}
                                    aria-invalid={Boolean(errors[`schedule.${day}.open`])}
                                    aria-describedby={errors[`schedule.${day}.open`] ? fieldErrorId(`schedule-${day}-open`) : undefined}
                                    className="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40"
                                />
                                <span className="text-gray-400">—</span>
                                <input
                                    id={`schedule-${day}-close`}
                                    name={`schedule[${day}][close]`}
                                    type="time"
                                    value={data.schedule[day]?.close || ''}
                                    onChange={(e) => handleTimeChange(day, 'close', e.target.value)}
                                    disabled={!data.schedule[day]?.isOpen}
                                    aria-label={`Hora de cierre de ${label}`}
                                    aria-invalid={Boolean(errors[`schedule.${day}.close`])}
                                    aria-describedby={errors[`schedule.${day}.close`] ? fieldErrorId(`schedule-${day}-close`) : undefined}
                                    className="px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm disabled:opacity-40"
                                />
                            </div>
                            <div className="sr-only" aria-live="polite">
                                {errors[`schedule.${day}.open`] ? (
                                    <span id={fieldErrorId(`schedule-${day}-open`)}>{errors[`schedule.${day}.open`]}</span>
                                ) : null}
                                {errors[`schedule.${day}.close`] ? (
                                    <span id={fieldErrorId(`schedule-${day}-close`)}>{errors[`schedule.${day}.close`]}</span>
                                ) : null}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="flex justify-end mt-6">
                <button
                    type="submit"
                    disabled={processing}
                    className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50"
                >
                    {processing ? 'Guardando...' : 'Guardar Horarios'}
                </button>
            </div>
        </form>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// SECURITY TAB (Isolated Form 3)
// ═══════════════════════════════════════════════════════════════════════════
function SecurityTab() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={handleSubmit}>
            <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                <h2 className="font-semibold text-gray-900 mb-4">🔒 Cambiar Contraseña</h2>
                <div className="space-y-4 max-w-md">
                    <div>
                        <label htmlFor="settings-current-password" className="block text-sm font-medium text-gray-700 mb-1">Contraseña Actual</label>
                        <input
                            id="settings-current-password"
                            name="current_password"
                            type="password"
                            autoComplete="current-password"
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            aria-label="Contrasena actual"
                            aria-invalid={Boolean(errors.current_password)}
                            aria-describedby={describedBy('current_password', errors)}
                            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 ${errors.current_password ? 'border-red-300' : 'border-gray-200'}`}
                        />
                        {errors.current_password && <p id={fieldErrorId('current_password')} role="alert" className="mt-1 text-sm text-red-500">{errors.current_password}</p>}
                    </div>

                    <div>
                        <label htmlFor="settings-password" className="block text-sm font-medium text-gray-700 mb-1">Nueva Contraseña</label>
                        <input
                            id="settings-password"
                            name="password"
                            type="password"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            aria-label="Nueva contrasena"
                            aria-invalid={Boolean(errors.password)}
                            aria-describedby={describedBy('password', errors, fieldHelpId('password'))}
                            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 ${errors.password ? 'border-red-300' : 'border-gray-200'}`}
                        />
                        {errors.password ? (
                            <p id={fieldErrorId('password')} role="alert" className="mt-1 text-sm text-red-500">{errors.password}</p>
                        ) : (
                            <p id={fieldHelpId('password')} className="mt-1 text-xs text-gray-500">Usa una contrasena segura para proteger el panel.</p>
                        )}
                    </div>

                    <div>
                        <label htmlFor="settings-password-confirmation" className="block text-sm font-medium text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                        <input
                            id="settings-password-confirmation"
                            name="password_confirmation"
                            type="password"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            aria-label="Confirmar nueva contrasena"
                            aria-invalid={Boolean(errors.password_confirmation)}
                            aria-describedby={describedBy('password_confirmation', errors)}
                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                        />
                        {errors.password_confirmation && <p id={fieldErrorId('password_confirmation')} role="alert" className="mt-1 text-sm text-red-500">{errors.password_confirmation}</p>}
                    </div>
                </div>
            </div>

            <div className="flex justify-end mt-6">
                <button
                    type="submit"
                    disabled={processing}
                    className="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg transition-all disabled:opacity-50"
                >
                    {processing ? 'Actualizando...' : 'Actualizar Contraseña'}
                </button>
            </div>
        </form>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// HELPER COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
function InputWithIcon({
    id,
    name,
    label,
    icon,
    value,
    onChange,
    type = 'text',
    placeholder,
    autoComplete,
    inputMode,
    error,
}) {
    const normalizedLabel = String(label || 'field')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/(^-|-$)/g, '');
    const inputId = id || `settings-${normalizedLabel || 'field'}`;
    const inputName = name || normalizedLabel || inputId;

    return (
        <div>
            <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1">{label}</label>
            <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2" aria-hidden="true">{icon}</span>
                <input
                    id={inputId}
                    name={inputName}
                    type={type}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    autoComplete={autoComplete}
                    inputMode={inputMode}
                    aria-invalid={Boolean(error)}
                    aria-describedby={error ? fieldErrorId(inputName) : undefined}
                    className="w-full pl-10 pr-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                />
            </div>
            {error && <p id={fieldErrorId(inputName)} role="alert" className="mt-1 text-sm text-red-500">{error}</p>}
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function SettingsIndex({ tenant, schedule, dayLabels }) {
    const [activeTab, setActiveTab] = useState('profile');

    return (
        <TenantLayout>
            <Head title="Configuración" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <h1 className="text-xl font-bold text-gray-900">⚙️ Configuración</h1>
                        </div>
                    </div>
                </header>

                <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Tab Navigation */}
                    <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-1 mb-6">
                        <nav className="flex gap-1" role="tablist" aria-label="Secciones de configuracion legacy">
                            {TABS.map((tab) => (
                                <button
                                    key={tab.id}
                                    id={`settings-tab-${tab.id}`}
                                    type="button"
                                    role="tab"
                                    aria-selected={activeTab === tab.id}
                                    aria-controls={`settings-panel-${tab.id}`}
                                    onClick={() => setActiveTab(tab.id)}
                                    className={`flex-1 px-4 py-2.5 text-sm font-medium rounded-lg transition-colors ${activeTab === tab.id
                                        ? 'bg-blue-600 text-white shadow-sm'
                                        : 'text-gray-600 hover:bg-gray-100'
                                        }`}
                                >
                                    {tab.label}
                                </button>
                            ))}
                        </nav>
                    </div>

                    {/* Tab Content */}
                    <section
                        id="settings-panel-profile"
                        role="tabpanel"
                        aria-labelledby="settings-tab-profile"
                        hidden={activeTab !== 'profile'}
                    >
                        {activeTab === 'profile' && <ProfileTab tenant={tenant} />}
                    </section>
                    <section
                        id="settings-panel-schedule"
                        role="tabpanel"
                        aria-labelledby="settings-tab-schedule"
                        hidden={activeTab !== 'schedule'}
                    >
                        {activeTab === 'schedule' && <ScheduleTab schedule={schedule} dayLabels={dayLabels} />}
                    </section>
                    <section
                        id="settings-panel-security"
                        role="tabpanel"
                        aria-labelledby="settings-tab-security"
                        hidden={activeTab !== 'security'}
                    >
                        {activeTab === 'security' && <SecurityTab />}
                    </section>
                </main>
            </div>
        </TenantLayout>
    );
}
