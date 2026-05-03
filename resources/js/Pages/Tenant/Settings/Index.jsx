import { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Camera, Clock3, Globe, Lock, MapPin, MessageCircle, Phone, UserCircle } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Textarea } from '@/Components/ui/textarea';
import TenantLayout from '@/Layouts/TenantLayout';

const TABS = [
    { id: 'profile', label: 'Perfil', icon: UserCircle },
    { id: 'schedule', label: 'Horarios', icon: Clock3 },
    { id: 'security', label: 'Seguridad', icon: Lock },
];

const fieldErrorId = (field) => `${field}-error`;

function ProfileTab({ tenant }) {
    const { data, setData, post, processing, errors } = useForm({
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

    useEffect(() => () => {
        if (logoPreview?.startsWith('blob:')) URL.revokeObjectURL(logoPreview);
    }, [logoPreview]);
    useEffect(() => () => {
        if (coverPreview?.startsWith('blob:')) URL.revokeObjectURL(coverPreview);
    }, [coverPreview]);

    const onLogoChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setData('logo', file);
        if (logoPreview?.startsWith('blob:')) URL.revokeObjectURL(logoPreview);
        setLogoPreview(URL.createObjectURL(file));
    };
    const onCoverChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setData('cover', file);
        if (coverPreview?.startsWith('blob:')) URL.revokeObjectURL(coverPreview);
        setCoverPreview(URL.createObjectURL(file));
    };

    return (
        <form
            className="space-y-6"
            onSubmit={(e) => {
                e.preventDefault();
                post(route('tenant.settings.update-profile'), { forceFormData: true });
            }}
        >
            <Card>
                <CardHeader><CardTitle>Identidad del negocio</CardTitle></CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <label htmlFor="settings-cover" className="text-sm font-medium text-slate-700">Portada</label>
                            <Input id="settings-cover" type="file" accept="image/*" onChange={onCoverChange} />
                            {coverPreview ? <img src={coverPreview} alt="Vista previa de portada" className="h-24 w-full rounded-md object-cover" /> : null}
                        </div>
                        <div className="space-y-2">
                            <label htmlFor="settings-logo" className="text-sm font-medium text-slate-700">Logo</label>
                            <Input id="settings-logo" type="file" accept="image/*" onChange={onLogoChange} />
                            {logoPreview ? <img src={logoPreview} alt="Vista previa de logo" className="h-24 w-24 rounded-md object-cover" /> : null}
                        </div>
                    </div>
                    <LabeledInput id="settings-name" label="Nombre del negocio" value={data.name} onChange={(v) => setData('name', v)} error={errors.name} required />
                    <LabeledArea id="settings-description" label="Descripción corta" value={data.description} onChange={(v) => setData('description', v)} error={errors.description} rows={2} />
                    <LabeledArea id="settings-about" label="Historia del negocio" value={data.about} onChange={(v) => setData('about', v)} error={errors.about} rows={4} />
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Contacto y redes</CardTitle></CardHeader>
                <CardContent className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <IconInput id="settings-phone" label="Teléfono" icon={Phone} value={data.phone} onChange={(v) => setData('phone', v)} error={errors.phone} />
                    <IconInput id="settings-whatsapp" label="WhatsApp" icon={MessageCircle} value={data.whatsapp_number} onChange={(v) => setData('whatsapp_number', v)} error={errors.whatsapp_number} />
                    <div className="md:col-span-2">
                        <IconInput id="settings-address" label="Dirección" icon={MapPin} value={data.address} onChange={(v) => setData('address', v)} error={errors.address} />
                    </div>
                    <IconInput id="settings-instagram" type="url" label="Instagram" icon={Camera} value={data.instagram_url} onChange={(v) => setData('instagram_url', v)} error={errors.instagram_url} />
                    <IconInput id="settings-facebook" type="url" label="Facebook" icon={Globe} value={data.facebook_url} onChange={(v) => setData('facebook_url', v)} error={errors.facebook_url} />
                    <IconInput id="settings-tiktok" type="url" label="TikTok" icon={Globe} value={data.tiktok_url} onChange={(v) => setData('tiktok_url', v)} error={errors.tiktok_url} />
                    <IconInput id="settings-website" type="url" label="Sitio web" icon={Globe} value={data.website_url} onChange={(v) => setData('website_url', v)} error={errors.website_url} />
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>{processing ? 'Guardando...' : 'Guardar perfil'}</Button>
            </div>
        </form>
    );
}

function ScheduleTab({ schedule: initialSchedule, dayLabels }) {
    const { data, setData, put, processing } = useForm({ schedule: initialSchedule || {} });
    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                put(route('tenant.settings.update-schedule'));
            }}
            className="space-y-6"
        >
            <Card>
                <CardHeader><CardTitle>Horarios de atención</CardTitle></CardHeader>
                <CardContent className="space-y-3">
                    {Object.entries(dayLabels || {}).map(([day, label]) => (
                        <div key={day} className="grid gap-3 rounded-lg border border-slate-200 p-3 md:grid-cols-[140px_1fr_1fr_1fr] md:items-center">
                            <p className="text-sm font-medium text-slate-900">{label}</p>
                            <label className="inline-flex items-center gap-2 text-sm text-slate-700">
                                <input
                                    type="checkbox"
                                    checked={Boolean(data.schedule?.[day]?.isOpen)}
                                    onChange={() =>
                                        setData('schedule', {
                                            ...data.schedule,
                                            [day]: { ...data.schedule?.[day], isOpen: !data.schedule?.[day]?.isOpen },
                                        })
                                    }
                                />
                                Abierto
                            </label>
                            <Input
                                type="time"
                                value={data.schedule?.[day]?.open || ''}
                                disabled={!data.schedule?.[day]?.isOpen}
                                onChange={(e) =>
                                    setData('schedule', {
                                        ...data.schedule,
                                        [day]: { ...data.schedule?.[day], open: e.target.value },
                                    })
                                }
                            />
                            <Input
                                type="time"
                                value={data.schedule?.[day]?.close || ''}
                                disabled={!data.schedule?.[day]?.isOpen}
                                onChange={(e) =>
                                    setData('schedule', {
                                        ...data.schedule,
                                        [day]: { ...data.schedule?.[day], close: e.target.value },
                                    })
                                }
                            />
                        </div>
                    ))}
                </CardContent>
            </Card>
            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>{processing ? 'Guardando...' : 'Guardar horarios'}</Button>
            </div>
        </form>
    );
}

function SecurityTab() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    return (
        <form
            className="space-y-6"
            onSubmit={(e) => {
                e.preventDefault();
                put(route('password.update'), { preserveScroll: true, onSuccess: () => reset() });
            }}
        >
            <Card>
                <CardHeader><CardTitle>Cambiar contraseña</CardTitle></CardHeader>
                <CardContent className="max-w-md space-y-4">
                    <LabeledInput id="settings-current-password" type="password" label="Contraseña actual" value={data.current_password} onChange={(v) => setData('current_password', v)} error={errors.current_password} />
                    <LabeledInput id="settings-password" type="password" label="Nueva contraseña" value={data.password} onChange={(v) => setData('password', v)} error={errors.password} />
                    <LabeledInput id="settings-password-confirmation" type="password" label="Confirmar nueva contraseña" value={data.password_confirmation} onChange={(v) => setData('password_confirmation', v)} error={errors.password_confirmation} />
                </CardContent>
            </Card>
            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>{processing ? 'Actualizando...' : 'Actualizar contraseña'}</Button>
            </div>
        </form>
    );
}

function IconInput({ id, label, icon: Icon, value, onChange, error, type = 'text' }) {
    return (
        <div className="space-y-2">
            <label htmlFor={id} className="text-sm font-medium text-slate-700">{label}</label>
            <div className="relative">
                <Icon className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden="true" />
                <Input id={id} type={type} value={value || ''} onChange={(e) => onChange(e.target.value)} className="pl-9" aria-invalid={Boolean(error)} aria-describedby={error ? fieldErrorId(id) : undefined} />
            </div>
            {error ? <p id={fieldErrorId(id)} className="text-sm text-red-600">{error}</p> : null}
        </div>
    );
}

function LabeledInput({ id, label, value, onChange, error, type = 'text', required = false }) {
    return (
        <div className="space-y-2">
            <label htmlFor={id} className="text-sm font-medium text-slate-700">{label}{required ? ' *' : ''}</label>
            <Input id={id} type={type} value={value || ''} onChange={(e) => onChange(e.target.value)} aria-invalid={Boolean(error)} aria-describedby={error ? fieldErrorId(id) : undefined} />
            {error ? <p id={fieldErrorId(id)} className="text-sm text-red-600">{error}</p> : null}
        </div>
    );
}

function LabeledArea({ id, label, value, onChange, error, rows = 3 }) {
    return (
        <div className="space-y-2">
            <label htmlFor={id} className="text-sm font-medium text-slate-700">{label}</label>
            <Textarea id={id} rows={rows} value={value || ''} onChange={(e) => onChange(e.target.value)} aria-invalid={Boolean(error)} aria-describedby={error ? fieldErrorId(id) : undefined} />
            {error ? <p id={fieldErrorId(id)} className="text-sm text-red-600">{error}</p> : null}
        </div>
    );
}

export default function SettingsIndex({ tenant, schedule, dayLabels }) {
    const [activeTab, setActiveTab] = useState('profile');
    return (
        <TenantLayout>
            <Head title="Configuración" />
            <div className="space-y-6">
                <header className="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h1 className="text-2xl font-bold text-slate-900">Configuración</h1>
                    <p className="mt-1 text-sm text-slate-600">Administra identidad, horarios y seguridad desde un flujo consistente.</p>
                </header>
                <Card>
                    <CardContent className="pt-6">
                        <nav className="grid grid-cols-1 gap-2 sm:grid-cols-3" role="tablist" aria-label="Secciones de configuración">
                            {TABS.map((tab) => {
                                const Icon = tab.icon;
                                const isActive = activeTab === tab.id;
                                return (
                                    <button
                                        key={tab.id}
                                        type="button"
                                        role="tab"
                                        aria-selected={isActive}
                                        aria-controls={`settings-panel-${tab.id}`}
                                        onClick={() => setActiveTab(tab.id)}
                                        className={`inline-flex items-center justify-center gap-2 rounded-md px-3 py-2 text-sm font-medium ${isActive ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-700 hover:bg-slate-200'}`}
                                    >
                                        <Icon className="h-4 w-4" aria-hidden="true" />
                                        {tab.label}
                                    </button>
                                );
                            })}
                        </nav>
                    </CardContent>
                </Card>
                <section id="settings-panel-profile" role="tabpanel" hidden={activeTab !== 'profile'}>{activeTab === 'profile' ? <ProfileTab tenant={tenant} /> : null}</section>
                <section id="settings-panel-schedule" role="tabpanel" hidden={activeTab !== 'schedule'}>{activeTab === 'schedule' ? <ScheduleTab schedule={schedule} dayLabels={dayLabels} /> : null}</section>
                <section id="settings-panel-security" role="tabpanel" hidden={activeTab !== 'security'}>{activeTab === 'security' ? <SecurityTab /> : null}</section>
            </div>
        </TenantLayout>
    );
}
