/**
 * Pages/Tenant/LandingPage.jsx - Custom Tenant Landing Page Builder
 *
 * FEATURES:
 * - Drag-and-drop section builder (simplified)
 * - Live preview
 * - Section templates (Hero, Features, CTA, Gallery)
 * - SEO settings
 *
 * PROPS:
 * @param {Object} props.tenant - Tenant info
 * @param {Object} props.landingPageData - Existing landing page config
 * @param {boolean} props.isPro - PRO status (required for this feature)
 */

import { useState, useCallback } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import TenantLayout from '@/Layouts/TenantLayout';

// ═══════════════════════════════════════════════════════════════════════════
// SECTION TEMPLATES
// ═══════════════════════════════════════════════════════════════════════════
const SECTION_TEMPLATES = [
    {
        id: 'hero',
        name: 'Hero',
        icon: '🏠',
        description: 'Sección principal con título y botón de acción',
        defaultData: {
            headline: 'Bienvenido a nuestro negocio',
            subheadline: 'Descubre nuestros productos y servicios',
            buttonText: 'Ver Menú',
            buttonUrl: '#products',
        },
    },
    {
        id: 'features',
        name: 'Características',
        icon: '⭐',
        description: 'Lista de beneficios o servicios destacados',
        defaultData: {
            title: '¿Por qué elegirnos?',
            items: [
                { icon: '🚀', title: 'Rápido', description: 'Entrega en minutos' },
                { icon: '💎', title: 'Calidad', description: 'Ingredientes premium' },
                { icon: '💰', title: 'Precios justos', description: 'Sin cargos ocultos' },
            ],
        },
    },
    {
        id: 'gallery',
        name: 'Galería',
        icon: '🖼️',
        description: 'Galería de imágenes de tus productos',
        defaultData: {
            title: 'Nuestros Productos',
            images: [],
        },
    },
    {
        id: 'cta',
        name: 'Llamada a Acción',
        icon: '📢',
        description: 'Sección para promociones o contacto',
        defaultData: {
            title: '¿Listo para ordenar?',
            description: 'Contactanos ahora y recibe un descuento especial',
            buttonText: 'Contactar por WhatsApp',
            buttonUrl: 'https://wa.me/',
        },
    },
    {
        id: 'testimonials',
        name: 'Testimonios',
        icon: '💬',
        description: 'Opiniones de clientes satisfechos',
        defaultData: {
            title: 'Lo que dicen nuestros clientes',
            testimonials: [
                { name: 'María González', text: 'Excelente servicio, muy recomendado!' },
            ],
        },
    },
];

// ═══════════════════════════════════════════════════════════════════════════
// SECTION EDITOR MODAL
// ═══════════════════════════════════════════════════════════════════════════
function SectionEditor({ section, onSave, onClose }) {
    const [data, setData] = useState(section.data);

    const handleChange = (key, value) => {
        setData(prev => ({ ...prev, [key]: value }));
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="fixed inset-0 bg-gray-500/75" onClick={onClose} />
            <div className="flex min-h-full items-center justify-center p-4">
                <div className="relative bg-white rounded-2xl shadow-xl max-w-lg w-full p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-xl font-bold text-gray-900">
                            Editar {section.template.name}
                        </h3>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div className="space-y-4">
                        {section.template.id === 'hero' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Título Principal</label>
                                    <input
                                        type="text"
                                        value={data.headline}
                                        onChange={(e) => handleChange('headline', e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Subtítulo</label>
                                    <input
                                        type="text"
                                        value={data.subheadline}
                                        onChange={(e) => handleChange('subheadline', e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Texto del Botón</label>
                                    <input
                                        type="text"
                                        value={data.buttonText}
                                        onChange={(e) => handleChange('buttonText', e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl"
                                    />
                                </div>
                            </>
                        )}

                        {section.template.id === 'cta' && (
                            <>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Título</label>
                                    <input
                                        type="text"
                                        value={data.title}
                                        onChange={(e) => handleChange('title', e.target.value)}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                    <textarea
                                        value={data.description}
                                        onChange={(e) => handleChange('description', e.target.value)}
                                        rows={3}
                                        className="w-full px-4 py-3 border border-gray-200 rounded-xl"
                                    />
                                </div>
                            </>
                        )}
                    </div>

                    <div className="flex gap-3 mt-6">
                        <button
                            onClick={onClose}
                            className="flex-1 py-3 bg-gray-100 text-gray-700 font-semibold rounded-xl"
                        >
                            Cancelar
                        </button>
                        <button
                            onClick={() => onSave(data)}
                            className="flex-1 py-3 bg-blue-600 text-white font-semibold rounded-xl"
                        >
                            Guardar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// SECTION PREVIEW
// ═══════════════════════════════════════════════════════════════════════════
function SectionPreview({ section, onEdit, onDelete, onMoveUp, onMoveDown, isFirst, isLast }) {
    return (
        <div className="group relative bg-white border border-gray-200 rounded-xl p-4 hover:border-blue-300 transition-colors">
            {/* Actions */}
            <div className="absolute top-2 right-2 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                <button
                    onClick={onMoveUp}
                    disabled={isFirst}
                    className="p-1.5 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50"
                    title="Mover arriba"
                >
                    ↑
                </button>
                <button
                    onClick={onMoveDown}
                    disabled={isLast}
                    className="p-1.5 bg-gray-100 rounded-lg hover:bg-gray-200 disabled:opacity-50"
                    title="Mover abajo"
                >
                    ↓
                </button>
                <button
                    onClick={onEdit}
                    className="p-1.5 bg-blue-100 text-blue-600 rounded-lg hover:bg-blue-200"
                    title="Editar"
                >
                    ✏️
                </button>
                <button
                    onClick={onDelete}
                    className="p-1.5 bg-red-100 text-red-600 rounded-lg hover:bg-red-200"
                    title="Eliminar"
                >
                    🗑️
                </button>
            </div>

            {/* Content */}
            <div className="flex items-center gap-3">
                <span className="text-2xl">{section.template.icon}</span>
                <div>
                    <p className="font-semibold text-gray-900">{section.template.name}</p>
                    <p className="text-sm text-gray-500">{section.data.headline || section.data.title || 'Sin título'}</p>
                </div>
            </div>
        </div>
    );
}

// ═══════════════════════════════════════════════════════════════════════════
// MAIN PAGE
// ═══════════════════════════════════════════════════════════════════════════
export default function LandingPage({ tenant, landingPageData, isPro }) {
    const [sections, setSections] = useState(landingPageData?.sections || []);
    const [editingSection, setEditingSection] = useState(null);
    const [isSaving, setIsSaving] = useState(false);

    // Add new section
    const addSection = useCallback((template) => {
        const newSection = {
            id: Date.now(),
            template,
            data: { ...template.defaultData },
        };
        setSections(prev => [...prev, newSection]);
    }, []);

    // Update section
    const updateSection = useCallback((sectionId, newData) => {
        setSections(prev => prev.map(s =>
            s.id === sectionId ? { ...s, data: newData } : s
        ));
        setEditingSection(null);
    }, []);

    // Delete section
    const deleteSection = useCallback((sectionId) => {
        setSections(prev => prev.filter(s => s.id !== sectionId));
    }, []);

    // Move section
    const moveSection = useCallback((sectionId, direction) => {
        setSections(prev => {
            const index = prev.findIndex(s => s.id === sectionId);
            if (index === -1) return prev;

            const newIndex = direction === 'up' ? index - 1 : index + 1;
            if (newIndex < 0 || newIndex >= prev.length) return prev;

            const newSections = [...prev];
            [newSections[index], newSections[newIndex]] = [newSections[newIndex], newSections[index]];
            return newSections;
        });
    }, []);

    // Save landing page
    const handleSave = useCallback(() => {
        setIsSaving(true);
        router.put(
            route('tenant.landing-page.update'),
            { sections },
            {
                preserveScroll: true,
                onFinish: () => setIsSaving(false),
            }
        );
    }, [sections]);

    // PRO gate
    if (!isPro) {
        return (
            <TenantLayout>
                <Head title="Landing Page Builder" />
                <div className="min-h-screen bg-gray-50 flex items-center justify-center py-12">
                    <div className="text-center max-w-md">
                        <div className="w-24 h-24 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <span className="text-4xl">🔒</span>
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900 mb-2">Función PRO</h1>
                        <p className="text-gray-500 mb-6">
                            El constructor de landing pages está disponible solo para cuentas PRO.
                        </p>
                        <a
                            href={route('tenant.upgrade')}
                            className="inline-block px-6 py-3 bg-blue-600 text-white font-bold rounded-xl"
                        >
                            Upgrade a PRO
                        </a>
                    </div>
                </div>
            </TenantLayout>
        );
    }

    return (
        <TenantLayout>
            <Head title="Landing Page Builder" />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <header className="bg-white border-b border-gray-200 sticky top-0 z-40">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            <h1 className="text-xl font-bold text-gray-900">🎨 Landing Page Builder</h1>
                            <button
                                onClick={handleSave}
                                disabled={isSaving}
                                className="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-xl transition-colors disabled:opacity-50"
                            >
                                {isSaving ? 'Guardando...' : 'Guardar Cambios'}
                            </button>
                        </div>
                    </div>
                </header>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                        {/* Section Templates */}
                        <div className="lg:col-span-1">
                            <div className="bg-white rounded-2xl border border-gray-100 p-6 sticky top-24">
                                <h2 className="font-bold text-gray-900 mb-4">Agregar Sección</h2>
                                <div className="space-y-2">
                                    {SECTION_TEMPLATES.map((template) => (
                                        <button
                                            key={template.id}
                                            onClick={() => addSection(template)}
                                            className="w-full flex items-center gap-3 p-3 text-left bg-gray-50 hover:bg-blue-50 rounded-xl transition-colors"
                                        >
                                            <span className="text-2xl">{template.icon}</span>
                                            <div>
                                                <p className="font-semibold text-gray-900">{template.name}</p>
                                                <p className="text-xs text-gray-500">{template.description}</p>
                                            </div>
                                        </button>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {/* Section List */}
                        <div className="lg:col-span-2">
                            <div className="bg-white rounded-2xl border border-gray-100 p-6">
                                <h2 className="font-bold text-gray-900 mb-4">Tus Secciones</h2>

                                {sections.length === 0 ? (
                                    <div className="text-center py-12 text-gray-500">
                                        <p className="text-4xl mb-4">📄</p>
                                        <p>No tienes secciones aún.</p>
                                        <p className="text-sm">Haz clic en una plantilla para comenzar.</p>
                                    </div>
                                ) : (
                                    <div className="space-y-3">
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
                            </div>
                        </div>
                    </div>
                </main>

                {/* Editor Modal */}
                {editingSection && (
                    <SectionEditor
                        section={editingSection}
                        onSave={(data) => updateSection(editingSection.id, data)}
                        onClose={() => setEditingSection(null)}
                    />
                )}
            </div>
        </TenantLayout>
    );
}
