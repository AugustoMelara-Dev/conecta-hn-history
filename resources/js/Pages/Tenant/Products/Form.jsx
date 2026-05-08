import { useRef, useState } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, ImageIcon, Lock, Plus, Trash2, Upload } from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
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
import { Progress } from '@/Components/ui/progress';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import { Switch } from '@/Components/ui/switch';
import { Textarea } from '@/Components/ui/textarea';

const NO_CATEGORY = '__none__';

export default function ProductForm({ product, sections = [], stockOptions = {}, tenant }) {
    const isEditing = Boolean(product);

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
    const [showCategoryDialog, setShowCategoryDialog] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const fileInputRef = useRef(null);

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
                    setShowCategoryDialog(false);
                },
            }
        );
    };

    return (
        <TenantLayout>
            <Head title={isEditing ? 'Editar producto' : 'Nuevo producto'} />

            <div className="min-h-screen bg-background">
                <header className="sticky top-0 z-40 border-b bg-background/95 backdrop-blur">
                    <div className="mx-auto flex h-16 max-w-4xl items-center justify-between px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <Button variant="ghost" size="icon" asChild>
                                <Link href={route('tenant.products.index')} aria-label="Volver a productos">
                                    <ArrowLeft aria-hidden="true" />
                                </Link>
                            </Button>
                            <div>
                                <h1 className="text-xl font-semibold text-foreground">
                                    {isEditing ? 'Editar producto' : 'Nuevo producto'}
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Mantiene tu catalogo listo para vender.
                                </p>
                            </div>
                        </div>
                        {tenant?.is_pro ? <Badge variant="secondary">PRO</Badge> : null}
                    </div>
                </header>

                <main className="mx-auto max-w-4xl px-4 py-8 sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
                            <div className="flex flex-col gap-6 lg:col-span-2">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Informacion del producto</CardTitle>
                                        <CardDescription>
                                            Nombre, precio, descripcion y disponibilidad visible para clientes.
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <FieldGroup>
                                            <Field data-invalid={Boolean(errors.name)}>
                                                <FieldLabel htmlFor="name">Nombre del producto *</FieldLabel>
                                                <Input
                                                    id="name"
                                                    value={data.name}
                                                    onChange={(e) => setData('name', e.target.value)}
                                                    placeholder="Ej: Hamburguesa clasica"
                                                    aria-invalid={Boolean(errors.name)}
                                                />
                                                {errors.name ? (
                                                    <FieldDescription className="text-destructive">
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
                                                        onChange={(e) => setData('price', e.target.value)}
                                                        placeholder="0.00"
                                                        className="pl-10"
                                                        aria-invalid={Boolean(errors.price)}
                                                    />
                                                </div>
                                                {errors.price ? (
                                                    <FieldDescription className="text-destructive">
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
                                                    onChange={(e) => setData('description', e.target.value)}
                                                    placeholder="Describe tu producto..."
                                                />
                                            </Field>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <Field className="rounded-lg border bg-card p-4">
                                                    <div className="flex items-center justify-between gap-4">
                                                        <div>
                                                            <FieldLabel htmlFor="is-visible">Visibilidad</FieldLabel>
                                                            <FieldDescription>
                                                                {data.is_visible ? 'Visible en el micrositio' : 'Oculto para clientes'}
                                                            </FieldDescription>
                                                        </div>
                                                        <Switch
                                                            id="is-visible"
                                                            checked={Boolean(data.is_visible)}
                                                            onCheckedChange={(checked) => setData('is_visible', checked)}
                                                        />
                                                    </div>
                                                </Field>

                                                <Field>
                                                    <FieldLabel htmlFor="stock-status">Disponibilidad</FieldLabel>
                                                    <Select
                                                        value={data.stock_status}
                                                        onValueChange={(value) => setData('stock_status', value)}
                                                    >
                                                        <SelectTrigger id="stock-status">
                                                            <SelectValue placeholder="Selecciona estado" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                {Object.entries(stockOptions).map(([value, label]) => (
                                                                    <SelectItem key={value} value={value}>
                                                                        {label}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                </Field>
                                            </div>
                                        </FieldGroup>
                                    </CardContent>
                                </Card>

                                <Card className={!tenant?.is_pro ? 'opacity-75' : undefined}>
                                    <CardHeader>
                                        <div className="flex items-start justify-between gap-4">
                                            <div>
                                                <CardTitle>Organizacion y marketing</CardTitle>
                                                <CardDescription>
                                                    Categorias, destacados y etiquetas visibles para clientes.
                                                </CardDescription>
                                            </div>
                                            {tenant?.is_pro ? <Badge variant="secondary">PRO</Badge> : null}
                                        </div>
                                    </CardHeader>
                                    <CardContent className="relative">
                                        {!tenant?.is_pro ? (
                                            <div className="absolute inset-0 z-10 flex items-center justify-center rounded-b-lg bg-background/80 backdrop-blur-sm">
                                                <div className="flex max-w-xs flex-col items-center gap-3 text-center">
                                                    <Lock aria-hidden="true" className="text-muted-foreground" />
                                                    <div>
                                                        <p className="font-medium text-foreground">Funcionalidad PRO</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            Activa PRO para organizar el catalogo por categorias y destacar productos.
                                                        </p>
                                                    </div>
                                                    <Button size="sm" asChild>
                                                        <Link href={`/upgrade/${tenant?.slug}`}>Activar PRO</Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        ) : null}

                                        <FieldGroup>
                                            <Field>
                                                <FieldLabel htmlFor="menu-section">Categoria</FieldLabel>
                                                <div className="flex gap-2">
                                                    <Select
                                                        value={data.menu_section_id ? String(data.menu_section_id) : NO_CATEGORY}
                                                        onValueChange={(value) => setData('menu_section_id', value === NO_CATEGORY ? '' : value)}
                                                        disabled={!tenant?.is_pro}
                                                    >
                                                        <SelectTrigger id="menu-section">
                                                            <SelectValue placeholder="Sin categoria" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectGroup>
                                                                <SelectItem value={NO_CATEGORY}>Sin categoria</SelectItem>
                                                                {sections.map((section) => (
                                                                    <SelectItem key={section.id} value={String(section.id)}>
                                                                        {section.name}
                                                                    </SelectItem>
                                                                ))}
                                                            </SelectGroup>
                                                        </SelectContent>
                                                    </Select>
                                                    {tenant?.is_pro ? (
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="icon"
                                                            onClick={() => setShowCategoryDialog(true)}
                                                            aria-label="Nueva categoria"
                                                        >
                                                            <Plus aria-hidden="true" />
                                                        </Button>
                                                    ) : null}
                                                </div>
                                            </Field>

                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <Field className="rounded-lg border bg-card p-4">
                                                    <div className="flex items-center justify-between gap-4">
                                                        <div>
                                                            <FieldLabel htmlFor="is-featured">Destacado</FieldLabel>
                                                            <FieldDescription>Mostrar primero</FieldDescription>
                                                        </div>
                                                        <Switch
                                                            id="is-featured"
                                                            checked={Boolean(data.is_featured)}
                                                            onCheckedChange={(checked) => setData('is_featured', checked)}
                                                            disabled={!tenant?.is_pro}
                                                        />
                                                    </div>
                                                </Field>

                                                <Field className="rounded-lg border bg-card p-4">
                                                    <div className="flex items-center justify-between gap-4">
                                                        <div>
                                                            <FieldLabel htmlFor="is-new">Nuevo</FieldLabel>
                                                            <FieldDescription>Mostrar badge Nuevo</FieldDescription>
                                                        </div>
                                                        <Switch
                                                            id="is-new"
                                                            checked={Boolean(data.is_new)}
                                                            onCheckedChange={(checked) => setData('is_new', checked)}
                                                            disabled={!tenant?.is_pro}
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
                                        <CardTitle>Imagen</CardTitle>
                                        <CardDescription>Usa una imagen clara del producto.</CardDescription>
                                    </CardHeader>
                                    <CardContent className="flex flex-col gap-4">
                                        {imagePreview ? (
                                            <div className="relative aspect-square overflow-hidden rounded-lg bg-muted">
                                                <img
                                                    src={imagePreview}
                                                    alt="Vista previa del producto"
                                                    className="h-full w-full object-cover"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="icon"
                                                    onClick={handleRemoveImage}
                                                    className="absolute right-2 top-2"
                                                    aria-label="Quitar imagen"
                                                >
                                                    <Trash2 aria-hidden="true" />
                                                </Button>
                                            </div>
                                        ) : (
                                            <label className="flex aspect-square cursor-pointer flex-col items-center justify-center gap-3 rounded-lg border border-dashed bg-muted/40 text-center text-muted-foreground hover:bg-muted">
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleImageChange}
                                                    className="hidden"
                                                />
                                                <ImageIcon aria-hidden="true" />
                                                <span className="text-sm font-medium">Subir imagen</span>
                                                <span className="text-xs">Max. 1MB</span>
                                            </label>
                                        )}

                                        {progress ? (
                                            <div className="flex flex-col gap-2">
                                                <Progress value={progress.percentage} />
                                                <p className="text-center text-xs text-muted-foreground">
                                                    {progress.percentage}%
                                                </p>
                                            </div>
                                        ) : null}

                                        {errors.image ? (
                                            <p className="text-sm text-destructive">{errors.image}</p>
                                        ) : null}

                                        {imagePreview ? (
                                            <Button type="button" variant="outline" asChild>
                                                <label className="cursor-pointer">
                                                    <Upload data-icon="inline-start" />
                                                    Cambiar imagen
                                                    <input
                                                        type="file"
                                                        accept="image/*"
                                                        onChange={handleImageChange}
                                                        className="hidden"
                                                    />
                                                </label>
                                            </Button>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            </div>
                        </div>

                        <Separator />

                        <div className="flex items-center justify-end gap-3">
                            <Button type="button" variant="ghost" asChild>
                                <Link href={route('tenant.products.index')}>Cancelar</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Guardando...' : isEditing ? 'Guardar cambios' : 'Crear producto'}
                            </Button>
                        </div>
                    </form>
                </main>

                <Dialog open={showCategoryDialog} onOpenChange={setShowCategoryDialog}>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Nueva categoria</DialogTitle>
                            <DialogDescription>
                                Agrega una categoria para organizar tus productos PRO.
                            </DialogDescription>
                        </DialogHeader>
                        <Field>
                            <FieldLabel htmlFor="new-category-name">Nombre</FieldLabel>
                            <Input
                                id="new-category-name"
                                value={newCategoryName}
                                onChange={(e) => setNewCategoryName(e.target.value)}
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        handleCreateCategory();
                                    }
                                }}
                                placeholder="Ej: Postres"
                            />
                        </Field>
                        <DialogFooter className="gap-2 sm:gap-0">
                            <Button type="button" variant="outline" onClick={() => setShowCategoryDialog(false)}>
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
