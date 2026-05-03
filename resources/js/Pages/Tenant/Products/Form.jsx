import { useState, useRef } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import { LoadingState, FormSection, PageHeader, ResponsiveContainer, StatusBadge } from '@/Components/System';
import { cn } from '@/lib/utils';

const NO_CATEGORY_VALUE = '__none__';

export default function ProductForm({ product, sections = [], stockOptions = {}, tenant }) {
    const isEditing = !!product;

    const { data, setData, post, processing, errors, progress } = useForm({
        name: product?.name || '',
        price: product?.price || '',
        description: product?.description || '',
        is_visible: product?.is_visible ?? true,
        stock_status: product?.stock_status || 'in_stock',
        menu_section_id: product?.menu_section_id || '',
        is_featured: product?.is_featured ?? false,
        is_new: product?.is_new ?? false,
        image: null,
        _method: isEditing ? 'PUT' : undefined,
    });

    const [imagePreview, setImagePreview] = useState(product?.image_url || null);
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const fileInputRef = useRef(null);

    const fieldErrorClass = 'border-destructive focus-visible:ring-destructive';

    const handleImageChange = (e) => {
        const file = e.target.files?.[0];
        if (!file) {
            return;
        }

        setData('image', file);
        setImagePreview(URL.createObjectURL(file));
    };

    const handleRemoveImage = () => {
        setData('image', null);
        setImagePreview(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const handleSubmit = (e) => {
        e.preventDefault();

        const url = isEditing
            ? route('tenant.products.update', product.id)
            : route('tenant.products.store');

        post(url, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const handleCreateCategory = () => {
        if (!newCategoryName.trim()) {
            return;
        }

        router.post(
            route('tenant.menu-sections.store'),
            { name: newCategoryName },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setNewCategoryName('');
                    setShowCategoryModal(false);
                },
            }
        );
    };

    return (
        <TenantLayout>
            <Head title={isEditing ? 'Editar Producto' : 'Nuevo Producto'} />

            <div className="min-h-screen bg-muted/20">
                <PageHeader
                    title={isEditing ? 'Editar Producto' : 'Nuevo Producto'}
                    subtitle="Completa la ficha comercial sin romper catalogo ni checkout."
                    backHref={route('tenant.products.index')}
                    backLabel="Volver a productos"
                    badge={tenant?.is_pro ? <StatusBadge status="approved" label="PRO" /> : null}
                />

                <ResponsiveContainer size="5xl" paddedY>
                    <form onSubmit={handleSubmit} className="space-y-6" noValidate>
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            <div className="space-y-6 lg:col-span-2">
                                <FormSection title="Informacion del producto">
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="name">Nombre del producto *</FieldLabel>
                                                <Input
                                                    id="name"
                                                    name="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    placeholder="Ej: Hamburguesa clásica"
                                                    maxLength={120}
                                                    required
                                                    aria-invalid={errors.name ? 'true' : 'false'}
                                                    aria-describedby={errors.name ? 'name-error' : undefined}
                                                    className={cn(errors.name && fieldErrorClass)}
                                                />
                                                {errors.name && (
                                                    <FieldDescription id="name-error" role="alert" className="text-destructive">
                                                        {errors.name}
                                                    </FieldDescription>
                                                )}
                                            </Field>

                                            <Field>
                                                <FieldLabel htmlFor="price">Precio (Lempiras) *</FieldLabel>
                                                <div className="relative">
                                                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">L.</span>
                                                    <Input
                                                        id="price"
                                                        name="price"
                                                        type="number"
                                                        inputMode="decimal"
                                                        min="0"
                                                        step="0.01"
                                                        value={data.price}
                                                        onChange={(e) => setData('price', e.target.value)}
                                                        placeholder="0.00"
                                                        required
                                                        aria-invalid={errors.price ? 'true' : 'false'}
                                                        aria-describedby={errors.price ? 'price-error' : undefined}
                                                        className={cn('pl-10', errors.price && fieldErrorClass)}
                                                    />
                                                </div>
                                                {errors.price && (
                                                    <FieldDescription id="price-error" role="alert" className="text-destructive">
                                                        {errors.price}
                                                    </FieldDescription>
                                                )}
                                            </Field>

                                            <Field>
                                                <FieldLabel htmlFor="description">Descripción</FieldLabel>
                                                <Textarea
                                                    id="description"
                                                    name="description"
                                                    rows={4}
                                                    value={data.description}
                                                    onChange={(e) => setData('description', e.target.value)}
                                                    placeholder="Describe tu producto para que el cliente compre con confianza."
                                                    maxLength={1000}
                                                    aria-invalid={errors.description ? 'true' : 'false'}
                                                    aria-describedby={errors.description ? 'description-error' : 'description-help'}
                                                    className={cn(errors.description && fieldErrorClass)}
                                                />
                                                {errors.description ? (
                                                    <FieldDescription id="description-error" role="alert" className="text-destructive">
                                                        {errors.description}
                                                    </FieldDescription>
                                                ) : (
                                                    <FieldDescription id="description-help">
                                                        Incluye tamaño, ingredientes o variantes importantes.
                                                    </FieldDescription>
                                                )}
                                            </Field>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <Card className="border-dashed">
                                                    <CardContent className="flex items-center justify-between p-4">
                                                        <div>
                                                            <p className="text-sm font-medium text-foreground">Visibilidad</p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {data.is_visible ? 'Visible en catálogo público' : 'Oculto del catálogo público'}
                                                            </p>
                                                        </div>
                                                        <Switch
                                                            checked={data.is_visible}
                                                            onCheckedChange={(checked) => setData('is_visible', checked)}
                                                            aria-label="Cambiar visibilidad del producto"
                                                        />
                                                    </CardContent>
                                                </Card>

                                                <Field>
                                                    <FieldLabel htmlFor="stock-status">Disponibilidad</FieldLabel>
                                                    <Select
                                                        value={data.stock_status || 'in_stock'}
                                                        onValueChange={(value) => setData('stock_status', value)}
                                                    >
                                                        <SelectTrigger id="stock-status" aria-label="Disponibilidad del producto">
                                                            <SelectValue placeholder="Selecciona disponibilidad" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {Object.entries(stockOptions).map(([value, label]) => (
                                                                <SelectItem key={value} value={value}>
                                                                    {label}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                            </div>
                                        </FieldGroup>
                                </FormSection>

                                <FormSection
                                    title="Organizacion y marketing"
                                    description={!tenant?.is_pro ? 'Disponible en plan PRO. Se conserva visible para mantener compatibilidad con formularios existentes.' : undefined}
                                    className={cn(!tenant?.is_pro && 'opacity-80')}
                                >
                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="menu-section">Categoría</FieldLabel>
                                                <div className="flex flex-col gap-2 sm:flex-row">
                                                    <Select
                                                        value={data.menu_section_id ? String(data.menu_section_id) : NO_CATEGORY_VALUE}
                                                        onValueChange={(value) => setData('menu_section_id', value === NO_CATEGORY_VALUE ? '' : value)}
                                                        disabled={!tenant?.is_pro}
                                                    >
                                                        <SelectTrigger id="menu-section" className="w-full" aria-label="Categoría del producto">
                                                            <SelectValue placeholder="Sin categoría" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value={NO_CATEGORY_VALUE}>Sin categoría</SelectItem>
                                                            {sections.map((section) => (
                                                                <SelectItem key={section.id} value={String(section.id)}>
                                                                    {section.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>

                                                    {tenant?.is_pro && (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            onClick={() => setShowCategoryModal(true)}
                                                            className="shrink-0"
                                                            aria-label="Crear nueva categoría"
                                                        >
                                                            <Plus className="mr-2 h-4 w-4" aria-hidden="true" />
                                                            Nueva
                                                        </Button>
                                                    )}
                                                </div>
                                                {errors.menu_section_id && (
                                                    <FieldDescription className="text-destructive" role="alert">
                                                        {errors.menu_section_id}
                                                    </FieldDescription>
                                                )}
                                            </Field>

                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                                <Card className="border-dashed">
                                                    <CardContent className="flex items-center justify-between p-4">
                                                        <div>
                                                            <p className="text-sm font-medium">Destacado</p>
                                                            <p className="text-xs text-muted-foreground">Prioriza este producto en la vitrina.</p>
                                                        </div>
                                                        <Switch
                                                            checked={data.is_featured}
                                                            onCheckedChange={(checked) => tenant?.is_pro && setData('is_featured', checked)}
                                                            disabled={!tenant?.is_pro}
                                                            aria-label="Marcar producto como destacado"
                                                        />
                                                    </CardContent>
                                                </Card>
                                                <Card className="border-dashed">
                                                    <CardContent className="flex items-center justify-between p-4">
                                                        <div>
                                                            <p className="text-sm font-medium">Nuevo</p>
                                                            <p className="text-xs text-muted-foreground">Muestra badge de novedad en catálogo.</p>
                                                        </div>
                                                        <Switch
                                                            checked={data.is_new}
                                                            onCheckedChange={(checked) => tenant?.is_pro && setData('is_new', checked)}
                                                            disabled={!tenant?.is_pro}
                                                            aria-label="Marcar producto como nuevo"
                                                        />
                                                    </CardContent>
                                                </Card>
                                            </div>
                                        </FieldGroup>
                                </FormSection>
                            </div>

                            <div className="space-y-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-lg">Imagen</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {imagePreview ? (
                                            <div className="relative overflow-hidden rounded-lg border bg-muted">
                                                <img
                                                    src={imagePreview}
                                                    alt="Vista previa del producto"
                                                    className="aspect-square w-full object-cover"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    onClick={handleRemoveImage}
                                                    className="absolute right-2 top-2"
                                                >
                                                    Quitar
                                                </Button>
                                            </div>
                                        ) : (
                                            <label className="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed bg-muted/40 text-center transition-colors hover:bg-muted">
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleImageChange}
                                                    className="sr-only"
                                                    aria-label="Subir imagen del producto"
                                                />
                                                <span className="text-sm font-medium text-foreground">Subir imagen</span>
                                                <span className="text-xs text-muted-foreground">Máximo 1MB</span>
                                            </label>
                                        )}

                                        {progress && (
                                            <LoadingState variant="inline" title={`Subiendo imagen... ${progress.percentage}%`} />
                                        )}

                                        {errors.image && (
                                            <FieldDescription className="text-destructive" role="alert">
                                                {errors.image}
                                            </FieldDescription>
                                        )}

                                        {imagePreview && (
                                            <label className="block">
                                                <input
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleImageChange}
                                                    className="sr-only"
                                                    aria-label="Cambiar imagen del producto"
                                                />
                                                <Button type="button" variant="outline" className="w-full" asChild>
                                                    <span>Cambiar imagen</span>
                                                </Button>
                                            </label>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <div className="flex flex-col-reverse gap-3 border-t pt-4 sm:flex-row sm:items-center sm:justify-end">
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('tenant.products.index')}>Cancelar</Link>
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="w-full sm:w-auto"
                            >
                                {processing ? 'Guardando...' : isEditing ? 'Guardar cambios' : 'Crear producto'}
                            </Button>
                        </div>
                    </form>
                </ResponsiveContainer>

                <Dialog open={showCategoryModal} onOpenChange={setShowCategoryModal}>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Nueva categoría</DialogTitle>
                            <DialogDescription>
                                Crea una sección para organizar tus productos visibles.
                            </DialogDescription>
                        </DialogHeader>
                        <Field>
                            <FieldLabel htmlFor="new-category-name">Nombre</FieldLabel>
                            <Input
                                id="new-category-name"
                                name="new_category_name"
                                type="text"
                                value={newCategoryName}
                                onChange={(e) => setNewCategoryName(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && handleCreateCategory()}
                                placeholder="Ej: Postres"
                                autoFocus
                            />
                        </Field>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowCategoryModal(false)}>
                                Cancelar
                            </Button>
                            <Button type="button" onClick={handleCreateCategory}>
                                Crear
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </TenantLayout>
    );
}
