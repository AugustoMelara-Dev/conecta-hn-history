import { useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Image as ImageIcon, Plus, Upload, X } from 'lucide-react';

import AppPageHeader from '@/Components/App/AppPageHeader';
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
import { Field, FieldDescription, FieldGroup, FieldLabel } from '@/Components/ui/field';
import { Input } from '@/Components/ui/input';
import { Progress } from '@/Components/ui/progress';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Spinner } from '@/Components/ui/spinner';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/Components/ui/tooltip';

const NO_SECTION = '__no_section__';

export default function ProductForm({ product, sections = [], stockOptions = {}, tenant }) {
    const isEditing = Boolean(product);
    const fileInputRef = useRef(null);
    const replaceFileInputRef = useRef(null);
    const [imagePreview, setImagePreview] = useState(product?.image_url || null);
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');

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

    const handleImageChange = (event) => {
        const file = event.target.files?.[0];

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

        if (replaceFileInputRef.current) {
            replaceFileInputRef.current.value = '';
        }
    };

    const handleSubmit = (event) => {
        event.preventDefault();

        post(isEditing ? route('tenant.products.update', product.id) : route('tenant.products.store'), {
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
            },
        );
    };

    return (
        <TenantLayout>
            <Head title={isEditing ? 'Editar Producto' : 'Nuevo Producto'} />

            <div className="min-h-screen bg-muted/30">
                <AppPageHeader
                    title={isEditing ? 'Editar Producto' : 'Nuevo Producto'}
                    backHref={route('tenant.products.index')}
                    backLabel="Volver a productos"
                    meta={tenant?.is_pro ? (
                        <span className="rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground">
                            PRO
                        </span>
                    ) : null}
                    className="bg-white"
                />

                <main className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            <div className="flex flex-col gap-6 lg:col-span-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Informacion del producto</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <FieldGroup>
                                            <Field data-invalid={Boolean(errors.name)}>
                                                <FieldLabel htmlFor="name">Nombre del producto *</FieldLabel>
                                                <Input
                                                    id="name"
                                                    type="text"
                                                    value={data.name}
                                                    onChange={(event) => setData('name', event.target.value)}
                                                    placeholder="Ej: Hamburguesa clasica"
                                                    aria-invalid={Boolean(errors.name)}
                                                    aria-describedby={errors.name ? 'product-name-error' : undefined}
                                                />
                                                {errors.name ? (
                                                    <FieldDescription id="product-name-error" role="alert" className="text-destructive">
                                                        {errors.name}
                                                    </FieldDescription>
                                                ) : null}
                                            </Field>

                                            <Field data-invalid={Boolean(errors.price)}>
                                                <FieldLabel htmlFor="price">Precio (Lempiras) *</FieldLabel>
                                                <div className="relative">
                                                    <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm font-medium text-muted-foreground">
                                                        L.
                                                    </span>
                                                    <Input
                                                        id="price"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={data.price}
                                                        onChange={(event) => setData('price', event.target.value)}
                                                        placeholder="0.00"
                                                        className="pl-10"
                                                        aria-invalid={Boolean(errors.price)}
                                                        aria-describedby={errors.price ? 'product-price-error' : undefined}
                                                    />
                                                </div>
                                                {errors.price ? (
                                                    <FieldDescription id="product-price-error" role="alert" className="text-destructive">
                                                        {errors.price}
                                                    </FieldDescription>
                                                ) : null}
                                            </Field>

                                            <Field>
                                                <FieldLabel htmlFor="description">Descripcion</FieldLabel>
                                                <Textarea
                                                    id="description"
                                                    rows={3}
                                                    value={data.description}
                                                    onChange={(event) => setData('description', event.target.value)}
                                                    placeholder="Describe tu producto..."
                                                />
                                            </Field>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <Field className="rounded-lg border bg-muted/40 p-4">
                                                    <div className="flex items-center justify-between gap-4">
                                                        <div>
                                                            <FieldLabel htmlFor="product-visible">Visibilidad</FieldLabel>
                                                            <FieldDescription>{data.is_visible ? 'Visible' : 'Oculto'}</FieldDescription>
                                                        </div>
                                                        <Switch
                                                            id="product-visible"
                                                            checked={Boolean(data.is_visible)}
                                                            onCheckedChange={(checked) => setData('is_visible', checked)}
                                                            aria-label="Cambiar visibilidad del producto"
                                                        />
                                                    </div>
                                                </Field>

                                                <Field className="rounded-lg border bg-muted/40 p-4">
                                                    <FieldLabel>Disponibilidad</FieldLabel>
                                                    <Select value={data.stock_status} onValueChange={(value) => setData('stock_status', value)}>
                                                        <SelectTrigger aria-label="Disponibilidad del producto">
                                                            <SelectValue placeholder="Seleccionar disponibilidad" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {Object.entries(stockOptions).map(([value, label]) => (
                                                                    <SelectItem key={value} value={value}>{label}</SelectItem>
                                                                ))}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                            </div>
                                        </FieldGroup>
                                    </CardContent>
                                </Card>

                                <Card className={!tenant?.is_pro ? 'opacity-75' : ''}>
                                    <CardHeader>
                                        <div className="flex items-center justify-between gap-4">
                                            <CardTitle className="text-base">Organizacion y marketing</CardTitle>
                                            {tenant?.is_pro ? (
                                                <span className="rounded-md bg-secondary px-2 py-1 text-xs font-medium text-secondary-foreground">
                                                    PRO
                                                </span>
                                            ) : null}
                                        </div>
                                    </CardHeader>
                                    <CardContent>
                                        <FieldGroup>
                                            <Field data-disabled={!tenant?.is_pro}>
                                                <FieldLabel>Categoria</FieldLabel>
                                                <div className="flex gap-2">
                                                    <Select
                                                        disabled={!tenant?.is_pro}
                                                        value={String(data.menu_section_id || NO_SECTION)}
                                                        onValueChange={(value) => setData('menu_section_id', value === NO_SECTION ? '' : value)}
                                                    >
                                                        <SelectTrigger aria-label="Categoria del producto">
                                                            <SelectValue placeholder="Sin categoria" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                <SelectItem value={NO_SECTION}>Sin categoria</SelectItem>
                                                                {sections.map((section) => (
                                                                    <SelectItem key={section.id} value={String(section.id)}>
                                                                        {section.name}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>

                                                    {tenant?.is_pro ? (
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button
                                                                        type="button"
                                                                        variant="outline"
                                                                        size="icon"
                                                                        aria-label="Crear nueva categoria"
                                                                        onClick={() => setShowCategoryModal(true)}
                                                                    >
                                                                        <Plus aria-hidden="true" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>Nueva categoria</TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    ) : null}
                                                </div>
                                                {!tenant?.is_pro ? (
                                                    <FieldDescription>Activa PRO para organizar productos por secciones.</FieldDescription>
                                                ) : null}
                                            </Field>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <Field className="rounded-lg border bg-muted/40 p-4" data-disabled={!tenant?.is_pro}>
                                                    <div className="flex items-center justify-between gap-4">
                                                        <div>
                                                            <FieldLabel htmlFor="product-featured">Destacado</FieldLabel>
                                                            <FieldDescription>Mostrar primero</FieldDescription>
                                                        </div>
                                                        <Switch
                                                            id="product-featured"
                                                            checked={Boolean(data.is_featured)}
                                                            disabled={!tenant?.is_pro}
                                                            onCheckedChange={(checked) => setData('is_featured', checked)}
                                                            aria-label="Marcar producto como destacado"
                                                        />
                                                    </div>
                                                </Field>

                                                <Field className="rounded-lg border bg-muted/40 p-4" data-disabled={!tenant?.is_pro}>
                                                    <div className="flex items-center justify-between gap-4">
                                                        <div>
                                                            <FieldLabel htmlFor="product-new">Nuevo</FieldLabel>
                                                            <FieldDescription>Badge Nuevo</FieldDescription>
                                                        </div>
                                                        <Switch
                                                            id="product-new"
                                                            checked={Boolean(data.is_new)}
                                                            disabled={!tenant?.is_pro}
                                                            onCheckedChange={(checked) => setData('is_new', checked)}
                                                            aria-label="Marcar producto como nuevo"
                                                        />
                                                    </div>
                                                </Field>
                                            </div>
                                        </FieldGroup>
                                    </CardContent>
                                </Card>
                            </div>

                            <div className="flex flex-col gap-6">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="text-base">Imagen</CardTitle>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-4">
                                        {imagePreview ? (
                                            <div className="relative aspect-square overflow-hidden rounded-lg bg-muted">
                                                <img src={imagePreview} alt="Vista previa" className="h-full w-full object-cover" />
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <Button
                                                                type="button"
                                                                variant="destructive"
                                                                size="icon"
                                                                className="absolute right-2 top-2"
                                                                aria-label="Quitar imagen"
                                                                onClick={handleRemoveImage}
                                                            >
                                                                <X aria-hidden="true" />
                                                            </Button>
                                                        </TooltipTrigger>
                                                        <TooltipContent>Quitar imagen</TooltipContent>
                                                    </Tooltip>
                                                </TooltipProvider>
                                            </div>
                                        ) : (
                                            <label className="flex aspect-square cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed bg-muted/40 text-muted-foreground transition-colors hover:bg-muted">
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleImageChange}
                                                    className="sr-only"
                                                />
                                                <ImageIcon aria-hidden="true" className="size-10" />
                                                <span className="mt-2 text-sm font-medium">Subir imagen</span>
                                                <span className="mt-1 text-xs">Max. 1MB</span>
                                            </label>
                                        )}

                                        {progress ? (
                                            <div className="flex flex-col gap-2" aria-live="polite">
                                                <Progress value={progress.percentage} aria-label={`Carga de imagen ${progress.percentage}%`} />
                                                <p className="text-center text-xs text-muted-foreground">{progress.percentage}%</p>
                                            </div>
                                        ) : null}

                                        {errors.image ? (
                                            <FieldDescription role="alert" className="text-destructive">
                                                {errors.image}
                                            </FieldDescription>
                                        ) : null}

                                        {imagePreview ? (
                                            <Button asChild variant="outline">
                                                <label className="cursor-pointer">
                                                    <input
                                                        ref={replaceFileInputRef}
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={handleImageChange}
                                                        className="sr-only"
                                                    />
                                                    <Upload data-icon="inline-start" aria-hidden="true" />
                                                    Cambiar imagen
                                                </label>
                                            </Button>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <div className="flex items-center justify-end gap-4 pt-4">
                            <Button asChild variant="ghost">
                                <Link href={route('tenant.products.index')}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? <Spinner data-icon="inline-start" aria-hidden="true" /> : null}
                                {processing ? 'Guardando...' : isEditing ? 'Guardar Cambios' : 'Crear Producto'}
                            </Button>
                        </div>
                    </form>
                </main>

                <Dialog open={showCategoryModal} onOpenChange={setShowCategoryModal}>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Nueva categoria</DialogTitle>
                            <DialogDescription>
                                Crea una seccion para organizar mejor tus productos visibles.
                            </DialogDescription>
                        </DialogHeader>
                        <Field>
                            <FieldLabel htmlFor="new-category-name">Nombre</FieldLabel>
                            <Input
                                id="new-category-name"
                                value={newCategoryName}
                                onChange={(event) => setNewCategoryName(event.target.value)}
                                placeholder="Ej: Entradas"
                                autoFocus
                            />
                        </Field>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setShowCategoryModal(false)}>
                                Cancelar
                            </Button>
                            <Button type="button" onClick={handleCreateCategory} disabled={!newCategoryName.trim()}>
                                Crear categoria
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </TenantLayout>
    );
}
