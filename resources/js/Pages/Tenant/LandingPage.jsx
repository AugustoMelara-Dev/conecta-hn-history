import { useCallback, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    FileText,
    Home,
    Image,
    Layers,
    Lock,
    Megaphone,
    MessageSquareQuote,
    Pencil,
    Plus,
    Save,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { toast } from 'sonner';

import { Alert, AlertDescription, AlertTitle } from '@/Components/ui/alert';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
} from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Separator } from '@/Components/ui/separator';
import { Spinner } from '@/Components/ui/spinner';
import { Textarea } from '@/Components/ui/textarea';
import TenantLayout from '@/Layouts/TenantLayout';

const SECTION_TEMPLATES = [
    {
        id: 'hero',
        name: 'Portada',
        icon: 'home',
        description: 'Titulo principal, mensaje corto y boton de accion.',
        defaultData: {
            headline: 'Bienvenido a nuestro negocio',
            subheadline: 'Descubre nuestros productos y servicios.',
            buttonText: 'Ver menu',
            buttonUrl: '#products',
        },
    },
    {
        id: 'features',
        name: 'Beneficios',
        icon: 'sparkles',
        description: 'Razones claras para elegir este negocio.',
        defaultData: {
            title: 'Por que elegirnos',
            items: [
                { icon: 'fast', title: 'Rapido', description: 'Atencion agil para tus pedidos.' },
                { icon: 'quality', title: 'Calidad', description: 'Productos preparados con cuidado.' },
                { icon: 'value', title: 'Precio justo', description: 'Opciones claras para cada cliente.' },
            ],
        },
    },
    {
        id: 'gallery',
        name: 'Galeria',
        icon: 'image',
        description: 'Espacio para mostrar productos o ambiente del negocio.',
        defaultData: {
            title: 'Nuestros productos',
            images: [],
        },
    },
    {
        id: 'cta',
        name: 'Llamado a la accion',
        icon: 'megaphone',
        description: 'Promocion, contacto o invitacion directa.',
        defaultData: {
            title: 'Listo para ordenar',
            description: 'Contactanos ahora y revisamos tu pedido.',
            buttonText: 'Contactar por WhatsApp',
            buttonUrl: 'https://wa.me/',
        },
    },
    {
        id: 'testimonials',
        name: 'Testimonios',
        icon: 'quote',
        description: 'Opiniones de clientes satisfechos.',
        defaultData: {
            title: 'Lo que dicen nuestros clientes',
            testimonials: [
                { name: 'Maria Gonzalez', text: 'Excelente servicio, muy recomendado.' },
            ],
        },
    },
];

const TEMPLATE_ICONS = {
    cta: Megaphone,
    features: Sparkles,
    gallery: Image,
    hero: Home,
    testimonials: MessageSquareQuote,
};

function safeNamedRoute(name, fallback) {
    try {
        if (route().has(name)) {
            const url = route(name);

            if (typeof window !== 'undefined') {
                const parsedUrl = new URL(url, window.location.origin);

                return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;
            }

            return url;
        }
    } catch {
        return fallback;
    }

    return fallback;
}

function normalizeSections(sections = []) {
    return Array.isArray(sections)
        ? sections.map((section, index) => {
            const templateId = section.template?.id || section.type || section.template?.icon;
            const template = SECTION_TEMPLATES.find((item) => item.id === templateId) || section.template || SECTION_TEMPLATES[0];

            return {
                id: section.id || `${template.id}-${index}`,
                template,
                data: section.data || {},
            };
        })
        : [];
}

function SectionIcon({ icon: Icon }) {
    return (
        <span className="flex size-10 items-center justify-center rounded-md bg-muted text-muted-foreground">
            <Icon aria-hidden="true" />
        </span>
    );
}

function normalizeEditableItems(items, fallbackItems) {
    return Array.isArray(items) && items.length > 0 ? items : fallbackItems;
}

function SectionEditor({ section, open, onSave, onOpenChange }) {
    const [data, setData] = useState(section?.data || {});
    const templateId = section?.template?.id;
    const featureItems = normalizeEditableItems(data.items, section?.template?.defaultData?.items || []);
    const testimonials = normalizeEditableItems(data.testimonials, section?.template?.defaultData?.testimonials || []);

    const handleChange = (key, value) => {
        setData((current) => ({ ...current, [key]: value }));
    };

    const handleListChange = (collection, index, key, value) => {
        setData((current) => {
            const currentItems = Array.isArray(current[collection])
                ? current[collection]
                : section?.template?.defaultData?.[collection] || [];
            const nextItems = currentItems.map((item, itemIndex) => (
                itemIndex === index ? { ...item, [key]: value } : item
            ));

            return { ...current, [collection]: nextItems };
        });
    };

    const fieldId = (key) => `landing-${templateId || 'section'}-${key}`;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto" data-testid="landing-editor-dialog">
                <DialogHeader>
                    <DialogTitle>Editar {section?.template?.name}</DialogTitle>
                    <DialogDescription>
                        Ajusta el contenido visible de esta seccion. El contrato guardado sigue siendo `sections`.
                    </DialogDescription>
                </DialogHeader>

                <FieldGroup>
                    {templateId === 'hero' ? (
                        <>
                            <Field>
                                <FieldLabel htmlFor={fieldId('headline')}>Titulo principal</FieldLabel>
                                <Input
                                    id={fieldId('headline')}
                                    value={data.headline || ''}
                                    onChange={(event) => handleChange('headline', event.target.value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={fieldId('subheadline')}>Texto de apoyo</FieldLabel>
                                <Textarea
                                    id={fieldId('subheadline')}
                                    value={data.subheadline || ''}
                                    onChange={(event) => handleChange('subheadline', event.target.value)}
                                    rows={3}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={fieldId('buttonText')}>Texto del boton</FieldLabel>
                                <Input
                                    id={fieldId('buttonText')}
                                    value={data.buttonText || ''}
                                    onChange={(event) => handleChange('buttonText', event.target.value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={fieldId('buttonUrl')}>URL del boton</FieldLabel>
                                <Input
                                    id={fieldId('buttonUrl')}
                                    value={data.buttonUrl || ''}
                                    onChange={(event) => handleChange('buttonUrl', event.target.value)}
                                    placeholder="#products"
                                />
                                <FieldDescription id={`${fieldId('buttonUrl')}-description`}>
                                    Usa una ruta interna, un ancla o un enlace completo si ya existe.
                                </FieldDescription>
                            </Field>
                        </>
                    ) : null}

                    {templateId === 'cta' ? (
                        <>
                            <Field>
                                <FieldLabel htmlFor={fieldId('title')}>Titulo</FieldLabel>
                                <Input
                                    id={fieldId('title')}
                                    value={data.title || ''}
                                    onChange={(event) => handleChange('title', event.target.value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={fieldId('description')}>Descripcion</FieldLabel>
                                <Textarea
                                    id={fieldId('description')}
                                    value={data.description || ''}
                                    onChange={(event) => handleChange('description', event.target.value)}
                                    rows={3}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={fieldId('buttonText')}>Texto del boton</FieldLabel>
                                <Input
                                    id={fieldId('buttonText')}
                                    value={data.buttonText || ''}
                                    onChange={(event) => handleChange('buttonText', event.target.value)}
                                />
                            </Field>
                            <Field>
                                <FieldLabel htmlFor={fieldId('buttonUrl')}>URL del boton</FieldLabel>
                                <Input
                                    id={fieldId('buttonUrl')}
                                    value={data.buttonUrl || ''}
                                    onChange={(event) => handleChange('buttonUrl', event.target.value)}
                                    placeholder="https://wa.me/"
                                />
                                <FieldDescription id={`${fieldId('buttonUrl')}-description`}>
                                    Mantiene el enlace guardado dentro de la misma estructura de secciones.
                                </FieldDescription>
                            </Field>
                        </>
                    ) : null}

                    {['features', 'gallery', 'testimonials'].includes(templateId) ? (
                        <Field>
                            <FieldLabel htmlFor={fieldId('title')}>Titulo de la seccion</FieldLabel>
                            <Input
                                id={fieldId('title')}
                                value={data.title || ''}
                                onChange={(event) => handleChange('title', event.target.value)}
                            />
                            <FieldDescription>
                                Los elementos internos se conservan para no cambiar el contrato actual.
                            </FieldDescription>
                        </Field>
                    ) : null}

                    {templateId === 'features' ? (
                        <div className="flex flex-col gap-3" data-testid="landing-features-editor">
                            {featureItems.map((item, index) => (
                                <Card key={`feature-${index}`}>
                                    <CardHeader>
                                        <CardTitle className="text-base">Beneficio {index + 1}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <Field>
                                            <FieldLabel htmlFor={fieldId(`feature-${index}-title`)}>Titulo</FieldLabel>
                                            <Input
                                                id={fieldId(`feature-${index}-title`)}
                                                value={item.title || ''}
                                                onChange={(event) => handleListChange('items', index, 'title', event.target.value)}
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor={fieldId(`feature-${index}-description`)}>Descripcion</FieldLabel>
                                            <Textarea
                                                id={fieldId(`feature-${index}-description`)}
                                                value={item.description || ''}
                                                onChange={(event) => handleListChange('items', index, 'description', event.target.value)}
                                                rows={2}
                                            />
                                        </Field>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : null}

                    {templateId === 'gallery' ? (
                        <Alert data-testid="landing-gallery-editor-note">
                            <Image aria-hidden="true" />
                            <AlertTitle>Galeria preparada</AlertTitle>
                            <AlertDescription>
                                Este corte mantiene la lista de imagenes actual para no cambiar la carga de archivos ni el contrato guardado.
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    {templateId === 'testimonials' ? (
                        <div className="flex flex-col gap-3" data-testid="landing-testimonials-editor">
                            {testimonials.map((testimonial, index) => (
                                <Card key={`testimonial-${index}`}>
                                    <CardHeader>
                                        <CardTitle className="text-base">Testimonio {index + 1}</CardTitle>
                                    </CardHeader>
                                    <CardContent className="grid gap-4">
                                        <Field>
                                            <FieldLabel htmlFor={fieldId(`testimonial-${index}-name`)}>Nombre</FieldLabel>
                                            <Input
                                                id={fieldId(`testimonial-${index}-name`)}
                                                value={testimonial.name || ''}
                                                onChange={(event) => handleListChange('testimonials', index, 'name', event.target.value)}
                                            />
                                        </Field>
                                        <Field>
                                            <FieldLabel htmlFor={fieldId(`testimonial-${index}-text`)}>Opinion</FieldLabel>
                                            <Textarea
                                                id={fieldId(`testimonial-${index}-text`)}
                                                value={testimonial.text || ''}
                                                onChange={(event) => handleListChange('testimonials', index, 'text', event.target.value)}
                                                rows={3}
                                            />
                                        </Field>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    ) : null}
                </FieldGroup>

                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                        Cancelar
                    </Button>
                    <Button type="button" onClick={() => onSave(data)}>
                        <Save data-icon="inline-start" aria-hidden="true" />
                        Guardar seccion
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function SectionPreview({ section, onEdit, onDelete, onMoveUp, onMoveDown, isFirst, isLast }) {
    const Icon = TEMPLATE_ICONS[section.template.id] || FileText;
    const title = section.data.headline || section.data.title || 'Sin titulo';

    return (
        <Card data-testid="landing-section-card">
            <CardHeader>
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex items-start gap-3">
                        <SectionIcon icon={Icon} />
                        <div className="flex flex-col gap-1">
                            <CardTitle className="text-lg">{section.template.name}</CardTitle>
                            <CardDescription>{title}</CardDescription>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={onMoveUp}
                            disabled={isFirst}
                            aria-label="Mover seccion arriba"
                        >
                            <ArrowUp aria-hidden="true" />
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            onClick={onMoveDown}
                            disabled={isLast}
                            aria-label="Mover seccion abajo"
                        >
                            <ArrowDown aria-hidden="true" />
                        </Button>
                        <Button type="button" variant="outline" size="icon" onClick={onEdit} aria-label="Editar seccion">
                            <Pencil aria-hidden="true" />
                        </Button>
                        <Button type="button" variant="outline" size="icon" onClick={onDelete} aria-label="Eliminar seccion">
                            <Trash2 aria-hidden="true" />
                        </Button>
                    </div>
                </div>
            </CardHeader>
        </Card>
    );
}

function LandingLivePreview({ sections, tenantName }) {
    const hero = sections.find((section) => section.template.id === 'hero');
    const featureSection = sections.find((section) => section.template.id === 'features');
    const cta = sections.find((section) => section.template.id === 'cta');
    const heroData = hero?.data || {};
    const featureItems = Array.isArray(featureSection?.data?.items)
        ? featureSection.data.items.slice(0, 3)
        : [];
    const ctaData = cta?.data || {};

    return (
        <Card className="overflow-hidden" data-testid="landing-live-preview">
            <CardHeader>
                <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div className="flex flex-col gap-1">
                        <CardTitle>Vista previa</CardTitle>
                        <CardDescription>
                            Una lectura rapida de como se sentira la landing publica.
                        </CardDescription>
                    </div>
                    <Badge variant="secondary">Borrador</Badge>
                </div>
            </CardHeader>
            <CardContent>
                <div className="rounded-lg border bg-muted/30 p-4 sm:p-6">
                    <div className="flex flex-col gap-5 rounded-md border bg-background p-5 shadow-sm">
                        <div className="flex flex-col gap-3">
                            <Badge variant="outline" className="w-fit">{tenantName || 'Tu negocio'}</Badge>
                            <h2 className="text-2xl font-semibold tracking-normal text-foreground sm:text-3xl">
                                {heroData.headline || 'Bienvenido a nuestro negocio'}
                            </h2>
                            <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                                {heroData.subheadline || 'Agrega una portada para presentar tu propuesta con claridad.'}
                            </p>
                            <div className="flex flex-wrap gap-2">
                                <Button type="button" size="sm">
                                    {heroData.buttonText || 'Ver catalogo'}
                                </Button>
                                <Button type="button" variant="outline" size="sm">
                                    Contactar
                                </Button>
                            </div>
                        </div>

                        <Separator />

                        <div className="grid gap-3 sm:grid-cols-3">
                            {(featureItems.length ? featureItems : [
                                { title: 'Atencion clara', description: 'Describe por que elegir este negocio.' },
                                { title: 'Oferta visible', description: 'Muestra beneficios sin inventar funciones.' },
                                { title: 'Accion directa', description: 'Guia al cliente hacia el siguiente paso.' },
                            ]).map((item, index) => (
                                <div key={`${item.title}-${index}`} className="rounded-md border bg-card p-3">
                                    <p className="text-sm font-medium text-card-foreground">{item.title}</p>
                                    <p className="mt-1 text-xs leading-5 text-muted-foreground">{item.description}</p>
                                </div>
                            ))}
                        </div>

                        <Alert>
                            <Megaphone aria-hidden="true" />
                            <AlertTitle>{ctaData.title || 'Listo para recibir clientes'}</AlertTitle>
                            <AlertDescription>
                                {ctaData.description || 'Agrega un llamado a la accion para convertir visitas en conversaciones.'}
                            </AlertDescription>
                        </Alert>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

export default function LandingPage({ tenant, landingPageData, isPro }) {
    const [sections, setSections] = useState(() => normalizeSections(landingPageData?.sections));
    const [editingSection, setEditingSection] = useState(null);
    const [isSaving, setIsSaving] = useState(false);
    const updateUrl = useMemo(() => safeNamedRoute('tenant.landing-page.update', '/workspace/landing-page'), []);
    const upgradeUrl = useMemo(() => safeNamedRoute('tenant.upgrade', '/workspace/upgrade'), []);

    const addSection = useCallback((template) => {
        const newSection = {
            id: `${template.id}-${Date.now()}`,
            template,
            data: { ...template.defaultData },
        };

        setSections((current) => [...current, newSection]);
    }, []);

    const updateSection = useCallback((sectionId, newData) => {
        setSections((current) => current.map((section) => (
            section.id === sectionId ? { ...section, data: newData } : section
        )));
        setEditingSection(null);
    }, []);

    const deleteSection = useCallback((sectionId) => {
        setSections((current) => current.filter((section) => section.id !== sectionId));
    }, []);

    const moveSection = useCallback((sectionId, direction) => {
        setSections((current) => {
            const index = current.findIndex((section) => section.id === sectionId);
            if (index === -1) {
                return current;
            }

            const nextIndex = direction === 'up' ? index - 1 : index + 1;
            if (nextIndex < 0 || nextIndex >= current.length) {
                return current;
            }

            const nextSections = [...current];
            [nextSections[index], nextSections[nextIndex]] = [nextSections[nextIndex], nextSections[index]];
            return nextSections;
        });
    }, []);

    const handleSave = useCallback(() => {
        setIsSaving(true);
        router.put(
            updateUrl,
            { sections },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
                onSuccess: () => toast.success('Landing guardada', {
                    description: 'Las secciones del negocio quedaron actualizadas.',
                }),
                onError: () => toast.error('No se pudo guardar la landing', {
                    description: 'Revisa los campos e intenta de nuevo.',
                }),
            }
        );
    }, [sections, updateUrl]);

    if (!isPro) {
        return (
            <TenantLayout>
                <Head title="Constructor de landing" />
                <main className="min-h-screen bg-background px-4 py-12" data-testid="landing-pro-gate">
                    <div className="mx-auto flex max-w-2xl flex-col gap-6">
                        <Alert>
                            <Lock aria-hidden="true" />
                            <AlertTitle>Constructor disponible en plan Pro</AlertTitle>
                            <AlertDescription>
                                Activa Pro para crear una pagina promocional propia para {tenant?.name || 'tu negocio'}.
                            </AlertDescription>
                        </Alert>
                        <Card>
                            <CardHeader>
                                <Badge variant="secondary" className="w-fit">Compat activo</Badge>
                                <CardTitle>Landing page del negocio</CardTitle>
                                <CardDescription>
                                    Esta herramienta permite componer secciones y guardarlas como configuracion del tenant.
                                </CardDescription>
                            </CardHeader>
                            <CardFooter>
                                <Button asChild>
                                    <a href={upgradeUrl}>Ver opciones Pro</a>
                                </Button>
                            </CardFooter>
                        </Card>
                    </div>
                </main>
            </TenantLayout>
        );
    }

    return (
        <TenantLayout>
            <Head title="Constructor de landing" />

            <main className="min-h-screen bg-background" data-testid="landing-builder-page">
                <div className="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                            <div className="flex max-w-3xl flex-col gap-3">
                                <Badge variant="secondary" className="w-fit">Compat activo</Badge>
                                <div className="flex flex-col gap-2">
                                    <CardTitle className="text-3xl">Constructor de landing</CardTitle>
                                    <CardDescription>
                                        Arma una pagina promocional para {tenant?.name || 'tu negocio'} con secciones simples y guardado controlado.
                                    </CardDescription>
                                </div>
                            </div>
                            <Button type="button" onClick={handleSave} disabled={isSaving} data-testid="landing-save">
                                {isSaving ? <Spinner data-icon="inline-start" /> : <Save data-icon="inline-start" aria-hidden="true" />}
                                {isSaving ? 'Guardando' : 'Guardar cambios'}
                            </Button>
                        </CardHeader>
                    </Card>

                    <div className="grid gap-6 lg:grid-cols-[22rem_1fr]">
                        <Card className="h-fit lg:sticky lg:top-8">
                            <CardHeader>
                                <div className="flex items-start gap-3">
                                    <SectionIcon icon={Layers} />
                                    <div className="flex flex-col gap-1">
                                        <CardTitle>Agregar seccion</CardTitle>
                                        <CardDescription>
                                            Elige una plantilla y ajusta su contenido despues.
                                        </CardDescription>
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                {SECTION_TEMPLATES.map((template) => {
                                    const Icon = TEMPLATE_ICONS[template.id] || FileText;

                                    return (
                                        <Button
                                            key={template.id}
                                            type="button"
                                            variant="outline"
                                            className="h-auto justify-start gap-3 whitespace-normal p-4 text-left"
                                            onClick={() => addSection(template)}
                                            data-testid="landing-template-card"
                                        >
                                            <Icon data-icon="inline-start" aria-hidden="true" />
                                            <span className="flex flex-col gap-1">
                                                <span className="font-medium">{template.name}</span>
                                                <span className="text-sm font-normal text-muted-foreground">{template.description}</span>
                                            </span>
                                        </Button>
                                    );
                                })}
                            </CardContent>
                        </Card>

                        <div className="flex flex-col gap-6">
                            <Card>
                                <CardHeader>
                                    <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                        <div className="flex flex-col gap-1">
                                            <CardTitle>Tus secciones</CardTitle>
                                            <CardDescription>
                                                {sections.length > 0
                                                    ? `${sections.length} secciones listas para guardar.`
                                                    : 'Agrega una seccion para comenzar.'}
                                            </CardDescription>
                                        </div>
                                        <Badge variant="outline" aria-live="polite">
                                            {sections.length} secciones
                                        </Badge>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    {sections.length === 0 ? (
                                        <Alert aria-live="polite" data-testid="landing-empty-state">
                                            <FileText aria-hidden="true" />
                                            <AlertTitle>Sin secciones todavia</AlertTitle>
                                            <AlertDescription>
                                                Usa las plantillas de la izquierda para crear la primera seccion de la landing.
                                            </AlertDescription>
                                        </Alert>
                                    ) : (
                                        <div className="flex flex-col gap-3">
                                            {sections.map((section, index) => (
                                                <SectionPreview
                                                    key={section.id}
                                                    section={section}
                                                    onEdit={() => setEditingSection(section)}
                                                    onDelete={() => deleteSection(section.id)}
                                                    onMoveUp={() => moveSection(section.id, 'up')}
                                                    onMoveDown={() => moveSection(section.id, 'down')}
                                                    isFirst={index === 0}
                                                    isLast={index === sections.length - 1}
                                                />
                                            ))}
                                        </div>
                                    )}
                                </CardContent>
                                <CardFooter className="flex flex-col items-start gap-4">
                                    <Separator />
                                    <p className="text-sm text-muted-foreground">
                                        El guardado actualiza solo la configuracion de secciones de este negocio.
                                    </p>
                                </CardFooter>
                            </Card>

                            <LandingLivePreview sections={sections} tenantName={tenant?.name} />
                        </div>
                    </div>
                </div>

                {editingSection ? (
                    <SectionEditor
                        key={editingSection.id}
                        section={editingSection}
                        open={Boolean(editingSection)}
                        onSave={(data) => updateSection(editingSection.id, data)}
                        onOpenChange={(open) => {
                            if (!open) {
                                setEditingSection(null);
                            }
                        }}
                    />
                ) : null}
            </main>
        </TenantLayout>
    );
}
