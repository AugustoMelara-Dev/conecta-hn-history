import { useEffect, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Camera, Lock, Settings, Upload } from 'lucide-react';

import AppPageHeader from '@/Components/App/AppPageHeader';
import TenantLayout from '@/Layouts/TenantLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Spinner } from '@/Components/ui/spinner';
import { Switch } from '@/Components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Textarea } from '@/Components/ui/textarea';

const fieldErrorId = (field) => `${field}-error`;

function describedBy(field, errors, helpId = null) {
    return errors?.[field] ? fieldErrorId(field) : helpId;
}

function FieldError({ field, errors }) {
    if (!errors?.[field]) {
        return null;
    }

    return (
        <FieldDescription id={fieldErrorId(field)} role="alert" className="text-destructive">
            {errors[field]}
        </FieldDescription>
    );
}

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
        if (logoPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(logoPreview);
        }
    }, [logoPreview]);

    useEffect(() => () => {
        if (coverPreview?.startsWith('blob:')) {
            URL.revokeObjectURL(coverPreview);
        }
    }, [coverPreview]);

    const handleLogoChange = (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setData('logo', file);
        setLogoPreview(URL.createObjectURL(file));
    };

    const handleCoverChange = (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            return;
        }

        setData('cover', file);
        setCoverPreview(URL.createObjectURL(file));
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(route('tenant.settings.update-profile'), { forceFormData: true });
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Card className="overflow-hidden">
                <div className="relative h-40 bg-primary">
                    {coverPreview ? (
                        <img src={coverPreview} alt="Portada del negocio" className="h-full w-full object-cover" />
                    ) : null}
                    <Button asChild size="sm" variant="secondary" className="absolute bottom-3 right-3">
                        <label className="cursor-pointer">
                            <input
                                id="cover-upload"
                                name="cover"
                                type="file"
                                accept="image/*"
                                onChange={handleCoverChange}
                                className="sr-only"
                                aria-label="Cambiar imagen de portada"
                            />
                            <Camera data-icon="inline-start" aria-hidden="true" />
                            Cambiar portada
                        </label>
                    </Button>
                </div>

                <CardContent className="pt-0">
                    <div className="relative -mt-12 mb-6 size-24 overflow-hidden rounded-lg border-4 border-background bg-primary text-primary-foreground shadow-lg">
                        {logoPreview ? (
                            <img src={logoPreview} alt="Logo del negocio" className="h-full w-full object-cover" />
                        ) : (
                            <div className="flex h-full w-full items-center justify-center text-2xl font-bold">
                                {data.name?.substring(0, 2) || 'T'}
                            </div>
                        )}
                        <Button asChild size="icon" className="absolute bottom-1 right-1 size-8" aria-label="Cambiar logotipo">
                            <label className="cursor-pointer">
                                <input
                                    id="logo-upload"
                                    name="logo"
                                    type="file"
                                    accept="image/*"
                                    onChange={handleLogoChange}
                                    className="sr-only"
                                    aria-label="Cambiar logotipo"
                                />
                                <Upload aria-hidden="true" />
                            </label>
                        </Button>
                    </div>

                    <FieldGroup>
                        <Field data-invalid={Boolean(errors.name)}>
                            <FieldLabel htmlFor="settings-name">Nombre del negocio *</FieldLabel>
                            <Input
                                id="settings-name"
                                name="name"
                                type="text"
                                autoComplete="organization"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(errors.name)}
                                aria-describedby={describedBy('name', errors)}
                            />
                            <FieldError field="name" errors={errors} />
                        </Field>

                        <Field data-invalid={Boolean(errors.description)}>
                            <FieldLabel htmlFor="settings-description">Descripcion corta</FieldLabel>
                            <Textarea
                                id="settings-description"
                                name="description"
                                rows={2}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                placeholder="Una frase que define tu negocio..."
                                aria-invalid={Boolean(errors.description)}
                                aria-describedby={describedBy('description', errors)}
                            />
                            <FieldError field="description" errors={errors} />
                        </Field>

                        <Field data-invalid={Boolean(errors.about)}>
                            <FieldLabel htmlFor="settings-about">Nuestra historia</FieldLabel>
                            <Textarea
                                id="settings-about"
                                name="about"
                                rows={4}
                                value={data.about}
                                onChange={(event) => setData('about', event.target.value)}
                                placeholder="Cuentale a tus clientes quienes son..."
                                aria-invalid={Boolean(errors.about)}
                                aria-describedby={describedBy('about', errors)}
                            />
                            <FieldError field="about" errors={errors} />
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Contacto y redes</CardTitle>
                </CardHeader>
                <CardContent>
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <ContactField id="settings-phone" name="phone" label="Telefono" value={data.phone} onChange={(value) => setData('phone', value)} placeholder="+504 9999-9999" autoComplete="tel" inputMode="tel" error={errors.phone} />
                        <ContactField id="settings-whatsapp" name="whatsapp_number" label="WhatsApp" value={data.whatsapp_number} onChange={(value) => setData('whatsapp_number', value)} placeholder="+504 9999-9999" autoComplete="tel" inputMode="tel" error={errors.whatsapp_number} />
                        <div className="md:col-span-2">
                            <ContactField id="settings-address" name="address" label="Direccion" value={data.address} onChange={(value) => setData('address', value)} placeholder="Colonia Kennedy, Tegucigalpa" autoComplete="street-address" error={errors.address} />
                        </div>
                        <ContactField id="settings-instagram" name="instagram_url" label="Instagram" value={data.instagram_url} onChange={(value) => setData('instagram_url', value)} type="url" inputMode="url" placeholder="https://instagram.com/tunegocio" error={errors.instagram_url} />
                        <ContactField id="settings-facebook" name="facebook_url" label="Facebook" value={data.facebook_url} onChange={(value) => setData('facebook_url', value)} type="url" inputMode="url" placeholder="https://facebook.com/tunegocio" error={errors.facebook_url} />
                        <ContactField id="settings-tiktok" name="tiktok_url" label="TikTok" value={data.tiktok_url} onChange={(value) => setData('tiktok_url', value)} type="url" inputMode="url" placeholder="https://tiktok.com/@tunegocio" error={errors.tiktok_url} />
                        <ContactField id="settings-website" name="website_url" label="Sitio web" value={data.website_url} onChange={(value) => setData('website_url', value)} type="url" inputMode="url" placeholder="https://tunegocio.com" error={errors.website_url} />
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                    {processing ? 'Guardando...' : 'Guardar perfil'}
                </Button>
            </div>
        </form>
    );
}

function ScheduleTab({ schedule: initialSchedule, dayLabels }) {
    const { data, setData, put, processing, errors } = useForm({
        schedule: initialSchedule || {},
    });

    const handleToggleDay = (day, checked) => {
        setData('schedule', {
            ...data.schedule,
            [day]: { ...data.schedule[day], isOpen: checked },
        });
    };

    const handleTimeChange = (day, field, value) => {
        setData('schedule', {
            ...data.schedule,
            [day]: { ...data.schedule[day], [field]: value },
        });
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        put(route('tenant.settings.update-schedule'));
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Horarios de atencion</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {Object.entries(dayLabels || {}).map(([day, label]) => {
                        const isOpen = Boolean(data.schedule[day]?.isOpen);

                        return (
                            <div
                                key={day}
                                className="flex flex-col gap-3 rounded-lg border bg-muted/40 p-4 md:flex-row md:items-center"
                            >
                                <div className="flex min-w-36 items-center justify-between gap-3">
                                    <span className="font-medium text-foreground">{label}</span>
                                    <Switch
                                        checked={isOpen}
                                        onCheckedChange={(checked) => handleToggleDay(day, checked)}
                                        aria-label={`${label}: ${isOpen ? 'abierto' : 'cerrado'}`}
                                    />
                                </div>

                                <span className="text-sm text-muted-foreground">{isOpen ? 'Abierto' : 'Cerrado'}</span>

                                <div className="grid flex-1 grid-cols-[1fr_auto_1fr] items-center gap-2 md:ml-auto md:max-w-sm">
                                    <Input
                                        id={`schedule-${day}-open`}
                                        name={`schedule[${day}][open]`}
                                        type="time"
                                        value={data.schedule[day]?.open || ''}
                                        onChange={(event) => handleTimeChange(day, 'open', event.target.value)}
                                        disabled={!isOpen}
                                        aria-label={`Hora de apertura de ${label}`}
                                        aria-invalid={Boolean(errors[`schedule.${day}.open`])}
                                        aria-describedby={errors[`schedule.${day}.open`] ? fieldErrorId(`schedule-${day}-open`) : undefined}
                                    />
                                    <span className="text-muted-foreground">-</span>
                                    <Input
                                        id={`schedule-${day}-close`}
                                        name={`schedule[${day}][close]`}
                                        type="time"
                                        value={data.schedule[day]?.close || ''}
                                        onChange={(event) => handleTimeChange(day, 'close', event.target.value)}
                                        disabled={!isOpen}
                                        aria-label={`Hora de cierre de ${label}`}
                                        aria-invalid={Boolean(errors[`schedule.${day}.close`])}
                                        aria-describedby={errors[`schedule.${day}.close`] ? fieldErrorId(`schedule-${day}-close`) : undefined}
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
                        );
                    })}
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                    {processing ? 'Guardando...' : 'Guardar horarios'}
                </Button>
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

    const handleSubmit = (event) => {
        event.preventDefault();
        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle className="flex items-center gap-2 text-base">
                        <Lock aria-hidden="true" className="size-4" />
                        Cambiar contrasena
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="max-w-md">
                        <PasswordField
                            id="settings-current-password"
                            name="current_password"
                            label="Contrasena actual"
                            autoComplete="current-password"
                            value={data.current_password}
                            onChange={(value) => setData('current_password', value)}
                            error={errors.current_password}
                        />
                        <PasswordField
                            id="settings-password"
                            name="password"
                            label="Nueva contrasena"
                            autoComplete="new-password"
                            value={data.password}
                            onChange={(value) => setData('password', value)}
                            error={errors.password}
                            help="Usa una contrasena segura para proteger el panel."
                        />
                        <PasswordField
                            id="settings-password-confirmation"
                            name="password_confirmation"
                            label="Confirmar nueva contrasena"
                            autoComplete="new-password"
                            value={data.password_confirmation}
                            onChange={(value) => setData('password_confirmation', value)}
                            error={errors.password_confirmation}
                        />
                    </FieldGroup>
                </CardContent>
            </Card>

            <div className="flex justify-end">
                <Button type="submit" disabled={processing}>
                    {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                    {processing ? 'Actualizando...' : 'Actualizar contrasena'}
                </Button>
            </div>
        </form>
    );
}

function ContactField({
    id,
    name,
    label,
    value,
    onChange,
    type = 'text',
    placeholder,
    autoComplete,
    inputMode,
    error,
}) {
    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input
                id={id}
                name={name}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
                autoComplete={autoComplete}
                inputMode={inputMode}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? fieldErrorId(name) : undefined}
            />
            {error ? (
                <FieldDescription id={fieldErrorId(name)} role="alert" className="text-destructive">
                    {error}
                </FieldDescription>
            ) : null}
        </Field>
    );
}

function PasswordField({
    id,
    name,
    label,
    value,
    onChange,
    autoComplete,
    error,
    help,
}) {
    const helpId = help ? `${name}-help` : null;

    return (
        <Field data-invalid={Boolean(error)}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Input
                id={id}
                name={name}
                type="password"
                autoComplete={autoComplete}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                aria-invalid={Boolean(error)}
                aria-describedby={error ? fieldErrorId(name) : helpId}
            />
            {error ? (
                <FieldDescription id={fieldErrorId(name)} role="alert" className="text-destructive">
                    {error}
                </FieldDescription>
            ) : help ? (
                <FieldDescription id={helpId}>{help}</FieldDescription>
            ) : null}
        </Field>
    );
}

export default function SettingsIndex({ tenant, schedule, dayLabels }) {
    const [activeTab, setActiveTab] = useState('profile');

    return (
        <TenantLayout>
            <Head title="Configuracion" />

            <div className="min-h-screen bg-muted/30">
                <AppPageHeader
                    title="Configuracion"
                    description="Mantiene el perfil, horarios y seguridad del negocio."
                    meta={<Settings aria-hidden="true" className="size-5 text-muted-foreground" />}
                    className="bg-white"
                />

                <main className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                    <Tabs value={activeTab} onValueChange={setActiveTab}>
                        <TabsList className="mb-6 grid h-auto w-full grid-cols-3" aria-label="Secciones de configuracion">
                            <TabsTrigger value="profile">Perfil</TabsTrigger>
                            <TabsTrigger value="schedule">Horarios</TabsTrigger>
                            <TabsTrigger value="security">Seguridad</TabsTrigger>
                        </TabsList>

                        <TabsContent value="profile">
                            <ProfileTab tenant={tenant} />
                        </TabsContent>
                        <TabsContent value="schedule">
                            <ScheduleTab schedule={schedule} dayLabels={dayLabels} />
                        </TabsContent>
                        <TabsContent value="security">
                            <SecurityTab />
                        </TabsContent>
                    </Tabs>
                </main>
            </div>
        </TenantLayout>
    );
}
