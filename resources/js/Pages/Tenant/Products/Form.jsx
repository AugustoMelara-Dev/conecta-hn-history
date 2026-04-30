/**
 * Tenant/Products/Form.jsx - Smart Product Form (Create/Edit)
 *
 * FEATURES:
 * - Dual Mode: Create (product=null) vs Edit (product={...})
 * - Method Spoofing: POST with _method: 'PUT' for file uploads
 * - Image Preview: URL.createObjectURL for instant feedback
 * - PRO-only fields with lock overlay
 *
 * PROPS (from ProductController@create or @edit):
 * @param {Object|null} props.product - Existing product data or null for creation
 * @param {Array} props.sections - Menu sections (categories) for PRO
 * @param {Object} props.stockOptions - { in_stock: 'Disponible', ... }
 * @param {Object} props.tenant - { id, name, slug, is_pro }
 */

import { useState, useRef } from 'react';
import { Head, Link, router, useForm } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Input } from '@/Components/ui/input';

// ═══════════════════════════════════════════════════════════════════════════
// MAIN COMPONENT
// ═══════════════════════════════════════════════════════════════════════════
export default function ProductForm({ product, sections = [], stockOptions = {}, tenant }) {
    const isEditing = !!product;

    // Inertia Form Hook
    const { data, setData, post, processing, errors, reset, progress } = useForm({
        name: product?.name || '',
        price: product?.price || '',
        description: product?.description || '',
        is_visible: product?.is_visible ?? true,
        stock_status: product?.stock_status || 'in_stock',
        menu_section_id: product?.menu_section_id || '',
        is_featured: product?.is_featured ?? false,
        is_new: product?.is_new ?? false,
        image: null,
        _method: isEditing ? 'PUT' : undefined, // Method spoofing for file uploads
    });

    // Image preview state
    const [imagePreview, setImagePreview] = useState(product?.image_url || null);
    const [showCategoryModal, setShowCategoryModal] = useState(false);
    const [newCategoryName, setNewCategoryName] = useState('');
    const fileInputRef = useRef(null);

    // ─────────────────────────────────────────────────────────────────────
    // HANDLERS
    // ─────────────────────────────────────────────────────────────────────

    const handleImageChange = (e) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            // Instant preview via URL.createObjectURL
            setImagePreview(URL.createObjectURL(file));
        }
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

        // Use POST for both create and update (with _method spoofing for PUT)
        const url = isEditing
            ? route('tenant.products.update', product.id)
            : route('tenant.products.store');

        post(url, {
            forceFormData: true, // Required for file uploads
            preserveScroll: true,
            onSuccess: () => {
                // Redirect handled by controller
            },
        });
    };

    const handleCreateCategory = () => {
        if (!newCategoryName.trim()) return;

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

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <div className="flex items-center gap-4">
                                <Link href={route('tenant.products.index')} className="text-gray-500 hover:text-gray-700">
                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 19l-7-7 7-7" />
                                    </svg>
                                </Link>
                                <h1 className="text-xl font-bold text-gray-900">
                                    {isEditing ? 'Editar Producto' : 'Nuevo Producto'}
                                </h1>
                            </div>
                            {tenant?.is_pro && (
                                <span className="px-2 py-1 bg-emerald-100 text-emerald-700 text-xs font-medium rounded-full">
                                    PRO
                                </span>
                            )}
                        </div>
                    </div>
                </header>

                <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                            {/* ═══════════════════════════════════════════════════════ */}
                            {/* LEFT COLUMN: Form Fields */}
                            {/* ═══════════════════════════════════════════════════════ */}
                            <div className="lg:col-span-2 space-y-6">
                                {/* Block 1: Basic Info */}
                                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                    <h2 className="font-semibold text-gray-900 mb-4">Información del Producto</h2>

                                    {/* Name */}
                                    <div className="mb-5">
                                        <label htmlFor="name" className="block text-sm font-medium text-gray-700 mb-2">
                                            Nombre del producto *
                                        </label>
                                        <input
                                            id="name"
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder="Ej: Hamburguesa Clásica"
                                            className={`w-full px-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                                                ${errors.name ? 'border-red-300 bg-red-50' : 'border-gray-200'}`}
                                        />
                                        {errors.name && <p className="mt-1.5 text-sm text-red-500">{errors.name}</p>}
                                    </div>

                                    {/* Price */}
                                    <div className="mb-5">
                                        <label htmlFor="price" className="block text-sm font-medium text-gray-700 mb-2">
                                            Precio (Lempiras) *
                                        </label>
                                        <div className="relative">
                                            <span className="absolute left-4 top-1/2 -translate-y-1/2 text-gray-500 font-medium">L.</span>
                                            <input
                                                id="price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={data.price}
                                                onChange={(e) => setData('price', e.target.value)}
                                                placeholder="0.00"
                                                className={`w-full pl-12 pr-4 py-3 bg-gray-50 border rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all
                                                    ${errors.price ? 'border-red-300 bg-red-50' : 'border-gray-200'}`}
                                            />
                                        </div>
                                        {errors.price && <p className="mt-1.5 text-sm text-red-500">{errors.price}</p>}
                                    </div>

                                    {/* Description */}
                                    <div className="mb-5">
                                        <label htmlFor="description" className="block text-sm font-medium text-gray-700 mb-2">
                                            Descripción
                                        </label>
                                        <textarea
                                            id="description"
                                            rows={3}
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Describe tu producto..."
                                            className="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all resize-none"
                                        />
                                    </div>

                                    {/* Visibility & Stock */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Visibility Toggle */}
                                        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                            <div>
                                                <h3 className="font-medium text-gray-900 text-sm">Visibilidad</h3>
                                                <p className="text-xs text-gray-500">{data.is_visible ? 'Visible' : 'Oculto'}</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => setData('is_visible', !data.is_visible)}
                                                className={`relative w-12 h-7 rounded-full transition-colors ${data.is_visible ? 'bg-emerald-500' : 'bg-gray-300'
                                                    }`}
                                            >
                                                <span className={`absolute top-1 w-5 h-5 bg-white rounded-full shadow transition-all ${data.is_visible ? 'right-1' : 'left-1'
                                                    }`} />
                                            </button>
                                        </div>

                                        {/* Stock Status */}
                                        <div className="p-4 bg-gray-50 rounded-xl">
                                            <label className="block text-sm font-medium text-gray-900 mb-2">Disponibilidad</label>
                                            <select
                                                value={data.stock_status}
                                                onChange={(e) => setData('stock_status', e.target.value)}
                                                className="w-full px-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                            >
                                                {Object.entries(stockOptions).map(([value, label]) => (
                                                    <option key={value} value={value}>{label}</option>
                                                ))}
                                            </select>
                                        </div>
                                    </div>
                                </div>

                                {/* Block 2: PRO Features */}
                                <div className={`bg-white rounded-xl shadow-sm border border-gray-100 p-6 relative ${!tenant?.is_pro ? 'opacity-60' : ''}`}>
                                    {/* PRO Lock Overlay */}
                                    {!tenant?.is_pro && (
                                        <div className="absolute inset-0 bg-gray-50/50 backdrop-blur-[1px] rounded-xl z-10 flex items-center justify-center">
                                            <div className="text-center p-4">
                                                <svg className="w-8 h-8 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                                <p className="text-sm font-medium text-gray-600 mb-2">🔒 Funcionalidad PRO</p>
                                                <Link
                                                    href={`/upgrade/${tenant?.slug}`}
                                                    className="inline-block px-4 py-1.5 bg-indigo-600 text-white text-xs font-medium rounded-lg hover:bg-indigo-700"
                                                >
                                                    Activar PRO
                                                </Link>
                                            </div>
                                        </div>
                                    )}

                                    <div className="flex items-center justify-between mb-4">
                                        <h2 className="font-semibold text-gray-900">Organización y Marketing</h2>
                                        {tenant?.is_pro && (
                                            <span className="px-2 py-0.5 bg-emerald-100 text-emerald-700 text-xs font-medium rounded-full">PRO</span>
                                        )}
                                    </div>

                                    {/* Category */}
                                    <div className="mb-5">
                                        <label className="block text-sm font-medium text-gray-700 mb-2">Categoría</label>
                                        <div className="flex gap-2">
                                            <select
                                                value={data.menu_section_id}
                                                onChange={(e) => setData('menu_section_id', e.target.value)}
                                                disabled={!tenant?.is_pro}
                                                className="flex-1 px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-blue-500"
                                            >
                                                <option value="">Sin categoría</option>
                                                {sections.map((section) => (
                                                    <option key={section.id} value={section.id}>{section.name}</option>
                                                ))}
                                            </select>
                                            {tenant?.is_pro && (
                                                <button
                                                    type="button"
                                                    onClick={() => setShowCategoryModal(true)}
                                                    className="px-4 py-3 bg-blue-50 text-blue-600 rounded-xl hover:bg-blue-100 transition-colors"
                                                    title="Nueva categoría"
                                                    aria-label="Crear nueva categoría"
                                                >
                                                    <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
                                                    </svg>
                                                </button>
                                            )}
                                        </div>
                                    </div>

                                    {/* Marketing Toggles */}
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                            <div>
                                                <h3 className="font-medium text-gray-900 text-sm">⭐ Destacado</h3>
                                                <p className="text-xs text-gray-500">Mostrar primero</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => tenant?.is_pro && setData('is_featured', !data.is_featured)}
                                                disabled={!tenant?.is_pro}
                                                className={`relative w-12 h-7 rounded-full transition-colors ${data.is_featured ? 'bg-amber-500' : 'bg-gray-300'
                                                    }`}
                                            >
                                                <span className={`absolute top-1 w-5 h-5 bg-white rounded-full shadow transition-all ${data.is_featured ? 'right-1' : 'left-1'
                                                    }`} />
                                            </button>
                                        </div>

                                        <div className="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                            <div>
                                                <h3 className="font-medium text-gray-900 text-sm">🆕 Nuevo</h3>
                                                <p className="text-xs text-gray-500">Badge "Nuevo"</p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => tenant?.is_pro && setData('is_new', !data.is_new)}
                                                disabled={!tenant?.is_pro}
                                                className={`relative w-12 h-7 rounded-full transition-colors ${data.is_new ? 'bg-blue-500' : 'bg-gray-300'
                                                    }`}
                                            >
                                                <span className={`absolute top-1 w-5 h-5 bg-white rounded-full shadow transition-all ${data.is_new ? 'right-1' : 'left-1'
                                                    }`} />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {/* ═══════════════════════════════════════════════════════ */}
                            {/* RIGHT COLUMN: Image Upload */}
                            {/* ═══════════════════════════════════════════════════════ */}
                            <div className="space-y-6">
                                <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
                                    <h2 className="font-semibold text-gray-900 mb-4">Imagen</h2>

                                    {/* Image Preview */}
                                    <div className="mb-4">
                                        {imagePreview ? (
                                            <div className="relative aspect-square bg-gray-100 rounded-xl overflow-hidden">
                                                <img
                                                    src={imagePreview}
                                                    alt="Vista previa"
                                                    className="w-full h-full object-cover"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={handleRemoveImage}
                                                    className="absolute top-2 right-2 p-1.5 bg-red-500 text-white rounded-full hover:bg-red-600"
                                                >
                                                    <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </button>
                                            </div>
                                        ) : (
                                            <label className="block aspect-square bg-gray-50 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:bg-gray-100 hover:border-gray-400 transition-colors">
                                                <input
                                                    ref={fileInputRef}
                                                    type="file"
                                                    accept="image/*"
                                                    onChange={handleImageChange}
                                                    className="hidden"
                                                />
                                                <div className="w-full h-full flex flex-col items-center justify-center text-gray-400">
                                                    <svg className="w-12 h-12 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1.5}
                                                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <span className="text-sm font-medium">Subir imagen</span>
                                                    <span className="text-xs mt-1">Máx. 1MB</span>
                                                </div>
                                            </label>
                                        )}
                                    </div>

                                    {/* Progress bar */}
                                    {progress && (
                                        <div className="mb-4">
                                            <div className="h-2 bg-gray-200 rounded-full overflow-hidden">
                                                <div
                                                    className="h-full bg-blue-500 transition-all"
                                                    style={{ width: `${progress.percentage}%` }}
                                                />
                                            </div>
                                            <p className="text-xs text-gray-500 mt-1 text-center">{progress.percentage}%</p>
                                        </div>
                                    )}

                                    {errors.image && <p className="mt-2 text-sm text-red-500">{errors.image}</p>}

                                    {/* Replace image button */}
                                    {imagePreview && (
                                        <label className="block mt-4">
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={handleImageChange}
                                                className="hidden"
                                            />
                                            <span className="inline-flex items-center justify-center w-full gap-2 px-4 py-2 bg-gray-100 text-gray-700 font-medium rounded-xl cursor-pointer hover:bg-gray-200 transition-colors">
                                                <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                                                </svg>
                                                Cambiar imagen
                                            </span>
                                        </label>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* Submit Button */}
                        <div className="flex items-center justify-end gap-4 pt-4">
                            <Link
                                href={route('tenant.products.index')}
                                className="px-6 py-3 text-gray-700 font-medium hover:text-gray-900"
                            >
                                Cancelar
                            </Link>
                            <button
                                type="submit"
                                disabled={processing}
                                className="inline-flex items-center justify-center gap-2 px-8 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl shadow-lg shadow-blue-500/25 transition-all disabled:opacity-50 disabled:cursor-wait"
                            >
                                {processing ? (
                                    <>
                                        <svg className="animate-spin w-5 h-5" fill="none" viewBox="0 0 24 24">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                        Guardando...
                                    </>
                                ) : (
                                    isEditing ? 'Guardar Cambios' : 'Crear Producto'
                                )}
                            </button>
                        </div>
                    </form>
                </main>

                <Dialog open={showCategoryModal} onOpenChange={setShowCategoryModal}>
                    <DialogContent className="sm:max-w-sm">
                        <DialogHeader>
                            <DialogTitle>Nueva Categoría</DialogTitle>
                            <DialogDescription>
                                Crea una sección para organizar mejor tus productos visibles.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="grid gap-2">
                            <label htmlFor="new-category-name" className="text-sm font-medium">
                                Nombre
                            </label>
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
                        </div>
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
