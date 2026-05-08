import { useEffect, useMemo, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    Building2,
    CalendarDays,
    Camera,
    Clock,
    Globe,
    ImageIcon,
    MapPin,
    MessageCircle,
    Phone,
    Save,
    Shield,
    Store,
    Upload,
} from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { Switch } from '@/Components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Textarea } from '@/Components/ui/textarea';

const DEFAULT_DAY_LABELS = {
    monday: 'Lunes',
    tuesday: 'Martes',
    wednesday: 'Miercoles',
    thursday: 'Jueves',
    friday: 'Viernes',
    saturday: 'Sabado',
    sunday: 'Domingo',
};

const SOCIAL_FIELDS = [
    { name: 'phone', label: 'Telefono', type: 'tel', placeholder: '+504 9999-9999', icon: Phone },
    { name: 'whatsapp_number', label: 'WhatsApp', type: 'tel', placeholder: '+504 9999-9999', icon: MessageCircle },
    { name: 'address', label: 'Direccion', type: 'text', placeholder: 'Colonia Kennedy, Tegucigalpa', icon: MapPin, wide: true },
    { name: 'instagram_url', label: 'Instagram', type: 'url', placeholder: 'https://instagram.com/tunegocio', icon: Camera },
    { name: 'facebook_url', label: 'Facebook', type: 'url', placeholder: 'https://facebook.com/tunegocio', icon: Store },
    { name: 'tiktok_url', label: 'TikTok', type: 'url', placeholder: 'https://tiktok.com/@tunegocio', icon: ImageIcon },
    { name: 'website_url', label: 'Sitio web', type: 'url', placeholder: 'https://tunegocio.com', icon: Globe },
];

function safeNamedRoute(name, fallback) {
    try {
        if (typeof route === 'function' && route().has(name)) {
            return route(name);
        }
    } catch (error) {
        return fallback;
    }

    return fallback;
}

function initials(name) {
    return String(name || 'TN')
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part.charAt(0))
        .join('')
        .toUpperCase() || 'TN';
}

function usePreview(initialValue) {
    const [preview, setPreview] = useState(initialValue || null);

    useEffect(() => {
        return () => {
            if (preview?.startsWith('blob:')) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    const updatePreview = (file) => {
        setPreview((current) => {
            if (current?.startsWith('blob:')) {
                URL.revokeObjectURL(current);
            }

            return file ? URL.createObjectURL(file) : initialValue || null;
        });
    };

    return [preview, updatePreview];
}

function fieldError(errors, name) {
    return errors?.[name];
}

function ErrorMessage({ id, error }) {
    if (!error) {
        return null;
    }

    return (
        <FieldDescription id={id} className="text-destructive">
            {error}
        </FieldDescription>
    );
}

function SubmitButton({ processing, children }) {
    return (
        <Button type="submit" disabled={processing}>
            {processing ? <Spinner data-icon="inline-start" /> : <Save data-icon="inline-start" />}
            {processing ? 'Guardando...' : children}
        </Button>
    );
}

function FileControl({ id, label, description, onChange, children, testId }) {
    return (
        <Field>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <FieldDescription>{description}</FieldDescription>
            <Button asChild variant="outline">
                <label htmlFor={id} className="cursor-pointer" data-testid={testId}>
                    <Upload data-icon="inline-start" />
                    {children}
                </label>
            </Button>
            <input id={id} type="file" accept="image/*" onChange={onChange} className="sr-only" />
        </Field>
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

    const [logoPreview, setLogoPreview] = usePreview(tenant?.logo_url || null);
    const [coverPreview, setCoverPreview] = usePreview(tenant?.cover_url || null);
    const profileAction = safeNamedRoute('tenant.settings.update-profile', '/workspace/settings/profile');

    const hasErrors = Object.keys(errors || {}).length > 0;

    const handleFile = (field, updatePreview) => (event) => {
        const file = event.target.files?.[0] || null;
        setData(field, file);
        updatePreview(file);
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        post(profileAction, { forceFormData: true, preserveScroll: true });
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6" data-testid="settings-profile-form">
            {hasErrors ? (
                <Alert variant="destructive" aria-live="polite">
                    <AlertTitle>Revisa la informacion</AlertTitle>
                    <AlertDescription>Hay campos que necesitan correccion antes de guardar.</AlertDescription>
                </Alert>
            ) : null}

            <Card>
                <CardHeader>
                    <CardTitle>Identidad publica</CardTitle>
                    <CardDescription>
                        Ajusta la imagen, el nombre y la descripcion que ven tus clientes.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-6">
                    <div className="overflow-hidden rounded-lg border bg-muted">
                        {coverPreview ? (
                            <img
                                src={coverPreview}
                                alt={`Portada de ${data.name || 'tu negocio'}`}
                                className="h-44 w-full object-cover"
                            />
                        ) : (
                            <div className="flex h-44 items-center justify-center text-sm text-muted-foreground">
                                Portada del negocio
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                        <div className="flex items-center gap-4">
                            <Avatar className="size-24 rounded-lg border bg-background">
                                <AvatarImage src={logoPreview || undefined} alt={`Logo de ${data.name || 'tu negocio'}`} />
                                <AvatarFallback className="rounded-lg text-xl font-semibold">{initials(data.name)}</AvatarFallback>
                            </Avatar>
                            <div>
                                <p className="text-sm font-medium text-foreground">Logo del negocio</p>
                                <p className="text-sm text-muted-foreground">
                                    Usa una imagen cuadrada y legible en tamanos pequenos.
                                </p>
                            </div>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <FileControl
                                id="settings-cover"
                                label="Portada"
                                description="Imagen horizontal para el micrositio."
                                onChange={handleFile('cover', setCoverPreview)}
                                testId="settings-cover-upload"
                            >
                                Cambiar portada
                            </FileControl>
                            <FileControl
                                id="settings-logo"
                                label="Logo"
                                description="Marca principal del negocio."
                                onChange={handleFile('logo', setLogoPreview)}
                                testId="settings-logo-upload"
                            >
                                Cambiar logo
                            </FileControl>
                        </div>
                    </div>

                    <Separator />

                    <FieldGroup>
                        <Field data-invalid={Boolean(fieldError(errors, 'name'))}>
                            <FieldLabel htmlFor="settings-name">Nombre del negocio *</FieldLabel>
                            <Input
                                id="settings-name"
                                value={data.name}
                                onChange={(event) => setData('name', event.target.value)}
                                aria-invalid={Boolean(fieldError(errors, 'name'))}
                                aria-describedby={fieldError(errors, 'name') ? 'settings-name-error' : undefined}
                                data-testid="settings-business-name"
                            />
                            <ErrorMessage id="settings-name-error" error={fieldError(errors, 'name')} />
                        </Field>

                        <Field data-invalid={Boolean(fieldError(errors, 'description'))}>
                            <FieldLabel htmlFor="settings-description">Descripcion corta</FieldLabel>
                            <Textarea
                                id="settings-description"
                                rows={2}
                                value={data.description}
                                onChange={(event) => setData('description', event.target.value)}
                                placeholder="Una frase clara sobre lo que vende tu negocio."
                                aria-invalid={Boolean(fieldError(errors, 'description'))}
                                aria-describedby={fieldError(errors, 'description') ? 'settings-description-error' : undefined}
                            />
                            <ErrorMessage id="settings-description-error" error={fieldError(errors, 'description')} />
                        </Field>

                        <Field data-invalid={Boolean(fieldError(errors, 'about'))}>
                            <FieldLabel htmlFor="settings-about">Historia del negocio</FieldLabel>
                            <Textarea
                                id="settings-about"
                                rows={5}
                                value={data.about}
                                onChange={(event) => setData('about', event.target.value)}
                                placeholder="Cuenta que haces, donde atiendes y que debe esperar un cliente."
                                aria-invalid={Boolean(fieldError(errors, 'about'))}
                                aria-describedby={fieldError(errors, 'about') ? 'settings-about-error' : undefined}
                            />
                            <ErrorMessage id="settings-about-error" error={fieldError(errors, 'about')} />
                        </Field>
                    </FieldGroup>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Contacto y redes</CardTitle>
                    <CardDescription>
                        Mantén estos datos actualizados para que el cliente pueda encontrarte sin friccion.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="grid gap-5 md:grid-cols-2">
                        {SOCIAL_FIELDS.map(({ name, label, type, placeholder, icon: Icon, wide }) => {
                            const error = fieldError(errors, name);
                            const id = `settings-${name}`;

                            return (
                                <Field key={name} className={wide ? 'md:col-span-2' : ''} data-invalid={Boolean(error)}>
                                    <FieldLabel htmlFor={id} className="inline-flex items-center gap-2">
                                        <Icon data-icon="inline-start" />
                                        {label}
                                    </FieldLabel>
                                    <Input
                                        id={id}
                                        type={type}
                                        value={data[name]}
                                        onChange={(event) => setData(name, event.target.value)}
                                        placeholder={placeholder}
                                        aria-invalid={Boolean(error)}
                                        aria-describedby={error ? `${id}-error` : undefined}
                                    />
                                    <ErrorMessage id={`${id}-error`} error={error} />
                                </Field>
                            );
                        })}
                    </FieldGroup>
                </CardContent>
                <CardFooter className="justify-end">
                    <SubmitButton processing={processing}>Guardar perfil</SubmitButton>
                </CardFooter>
            </Card>
        </form>
    );
}

function normalizeSchedule(initialSchedule, dayLabels) {
    const labels = Object.keys(dayLabels || {}).length ? dayLabels : DEFAULT_DAY_LABELS;

    return Object.keys(labels).reduce((carry, day) => {
        const value = initialSchedule?.[day] || {};
        carry[day] = {
            isOpen: Boolean(value.isOpen),
            open: value.open || '',
            close: value.close || '',
        };
        return carry;
    }, {});
}

function ScheduleTab({ schedule: initialSchedule, dayLabels }) {
    const labels = Object.keys(dayLabels || {}).length ? dayLabels : DEFAULT_DAY_LABELS;
    const normalizedSchedule = useMemo(
        () => normalizeSchedule(initialSchedule, labels),
        [initialSchedule, labels],
    );
    const { data, setData, put, processing, errors } = useForm({
        schedule: normalizedSchedule,
    });
    const scheduleAction = safeNamedRoute('tenant.settings.update-schedule', '/workspace/settings/schedule');

    const updateDay = (day, patch) => {
        setData('schedule', {
            ...data.schedule,
            [day]: {
                ...data.schedule[day],
                ...patch,
            },
        });
    };

    const handleSubmit = (event) => {
        event.preventDefault();
        put(scheduleAction, { preserveScroll: true });
    };

    return (
        <form onSubmit={handleSubmit} className="flex flex-col gap-6" data-testid="settings-schedule-form">
            <Card>
                <CardHeader>
                    <CardTitle>Horarios de atencion</CardTitle>
                    <CardDescription>
                        Define cuando tu negocio esta disponible para recibir clientes y solicitudes.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {Object.entries(labels).map(([day, label]) => {
                        const item = data.schedule[day] || { isOpen: false, open: '', close: '' };
                        const openError = fieldError(errors, `schedule.${day}.open`);
                        const closeError = fieldError(errors, `schedule.${day}.close`);
                        const openId = `settings-${day}-open`;
                        const closeId = `settings-${day}-close`;

                        return (
                            <Card key={day} className="border-border/80 shadow-none">
                                <CardContent className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_minmax(15rem,20rem)] lg:items-center">
                                    <Field className="gap-3">
                                        <div className="flex items-center justify-between gap-4">
                                            <div>
                                                <FieldLabel htmlFor={`settings-${day}-switch`} className="text-base">
                                                    {label}
                                                </FieldLabel>
                                                <FieldDescription>
                                                    {item.isOpen ? 'Abierto para atencion.' : 'Cerrado este dia.'}
                                                </FieldDescription>
                                            </div>
                                            <Switch
                                                id={`settings-${day}-switch`}
                                                checked={item.isOpen}
                                                onCheckedChange={(checked) => updateDay(day, { isOpen: checked })}
                                                aria-label={`Marcar ${label} como ${item.isOpen ? 'cerrado' : 'abierto'}`}
                                                data-testid={`settings-${day}-switch`}
                                            />
                                        </div>
                                    </Field>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field data-invalid={Boolean(openError)}>
                                            <FieldLabel htmlFor={openId}>Apertura</FieldLabel>
                                            <Input
                                                id={openId}
                                                type="time"
                                                value={item.open}
                                                onChange={(event) => updateDay(day, { open: event.target.value })}
                                                disabled={!item.isOpen}
                                                aria-invalid={Boolean(openError)}
                                                aria-describedby={openError ? `${openId}-error` : undefined}
                                            />
                                            <ErrorMessage id={`${openId}-error`} error={openError} />
                                        </Field>

                                        <Field data-invalid={Boolean(closeError)}>
                                            <FieldLabel htmlFor={closeId}>Cierre</FieldLabel>
                                            <Input
                                                id={closeId}
                                                type="time"
                                                value={item.close}
                                                onChange={(event) => updateDay(day, { close: event.target.value })}
                                                disabled={!item.isOpen}
                                                aria-invalid={Boolean(closeError)}
                                                aria-describedby={closeError ? `${closeId}-error` : undefined}
                                            />
                                            <ErrorMessage id={`${closeId}-error`} error={closeError} />
                                        </Field>
                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </CardContent>
                <CardFooter className="justify-end">
                    <SubmitButton processing={processing}>Guardar horarios</SubmitButton>
                </CardFooter>
            </Card>
        </form>
    );
}

function SecurityTab() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const passwordAction = safeNamedRoute('password.update', '/password');

    const handleSubmit = (event) => {
        event.preventDefault();
        put(passwordAction, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={handleSubmit} data-testid="settings-security-form">
            <Card>
                <CardHeader>
                    <CardTitle>Cambiar contrasena</CardTitle>
                    <CardDescription>
                        Actualiza tu acceso sin afectar la informacion publica del negocio.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <FieldGroup className="max-w-xl">
                        <Field data-invalid={Boolean(fieldError(errors, 'current_password'))}>
                            <FieldLabel htmlFor="settings-current-password">Contrasena actual</FieldLabel>
                            <Input
                                id="settings-current-password"
                                type="password"
                                value={data.current_password}
                                onChange={(event) => setData('current_password', event.target.value)}
                                aria-invalid={Boolean(fieldError(errors, 'current_password'))}
                                aria-describedby={fieldError(errors, 'current_password') ? 'settings-current-password-error' : undefined}
                            />
                            <ErrorMessage id="settings-current-password-error" error={fieldError(errors, 'current_password')} />
                        </Field>

                        <Field data-invalid={Boolean(fieldError(errors, 'password'))}>
                            <FieldLabel htmlFor="settings-password">Nueva contrasena</FieldLabel>
                            <Input
                                id="settings-password"
                                type="password"
                                value={data.password}
                                onChange={(event) => setData('password', event.target.value)}
                                aria-invalid={Boolean(fieldError(errors, 'password'))}
                                aria-describedby={fieldError(errors, 'password') ? 'settings-password-error' : undefined}
                            />
                            <ErrorMessage id="settings-password-error" error={fieldError(errors, 'password')} />
                        </Field>

                        <Field data-invalid={Boolean(fieldError(errors, 'password_confirmation'))}>
                            <FieldLabel htmlFor="settings-password-confirmation">Confirmar nueva contrasena</FieldLabel>
                            <Input
                                id="settings-password-confirmation"
                                type="password"
                                value={data.password_confirmation}
                                onChange={(event) => setData('password_confirmation', event.target.value)}
                                aria-invalid={Boolean(fieldError(errors, 'password_confirmation'))}
                                aria-describedby={
                                    fieldError(errors, 'password_confirmation')
                                        ? 'settings-password-confirmation-error'
                                        : undefined
                                }
                            />
                            <ErrorMessage
                                id="settings-password-confirmation-error"
                                error={fieldError(errors, 'password_confirmation')}
                            />
                        </Field>
                    </FieldGroup>
                </CardContent>
                <CardFooter className="justify-end">
                    <SubmitButton processing={processing}>Actualizar contrasena</SubmitButton>
                </CardFooter>
            </Card>
        </form>
    );
}

export default function SettingsIndex({ tenant, schedule, dayLabels }) {
    const [activeTab, setActiveTab] = useState('profile');

    return (
        <TenantLayout>
            <Head title="Configuracion" />

            <main className="min-h-screen bg-background" data-testid="settings-page">
                <div className="mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-8 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                            <div className="flex items-start gap-4">
                                <div className="rounded-lg border bg-muted p-3" aria-hidden="true">
                                    <Building2 />
                                </div>
                                <div>
                                    <CardTitle className="text-2xl">Configuracion del negocio</CardTitle>
                                    <CardDescription>
                                        Administra perfil, horarios y seguridad desde una sola pantalla.
                                    </CardDescription>
                                </div>
                            </div>
                            <Alert className="max-w-md">
                                <AlertTitle>Sin cambios de contrato</AlertTitle>
                                <AlertDescription>
                                    Esta vista conserva las rutas y payloads actuales de configuracion.
                                </AlertDescription>
                            </Alert>
                        </CardHeader>
                    </Card>

                    <Tabs value={activeTab} onValueChange={setActiveTab} className="flex flex-col gap-6">
                        <TabsList className="grid h-auto w-full grid-cols-1 gap-1 sm:grid-cols-3">
                            <TabsTrigger value="profile" data-testid="settings-profile-tab">
                                <Store data-icon="inline-start" />
                                Perfil
                            </TabsTrigger>
                            <TabsTrigger value="schedule" data-testid="settings-schedule-tab">
                                <Clock data-icon="inline-start" />
                                Horarios
                            </TabsTrigger>
                            <TabsTrigger value="security" data-testid="settings-security-tab">
                                <Shield data-icon="inline-start" />
                                Seguridad
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="profile" className="mt-0">
                            <ProfileTab tenant={tenant} />
                        </TabsContent>
                        <TabsContent value="schedule" className="mt-0">
                            <ScheduleTab schedule={schedule} dayLabels={dayLabels} />
                        </TabsContent>
                        <TabsContent value="security" className="mt-0">
                            <SecurityTab />
                        </TabsContent>
                    </Tabs>

                    <Card>
                        <CardContent className="flex flex-col gap-3 p-4 text-sm text-muted-foreground sm:flex-row sm:items-center">
                            <CalendarDays data-icon="inline-start" />
                            Los cambios se guardan en los endpoints existentes del modulo legacy de settings.
                        </CardContent>
                    </Card>
                </div>
            </main>
        </TenantLayout>
    );
}
