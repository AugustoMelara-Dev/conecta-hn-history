import { useState, useEffect } from 'react';
import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/Components/ui/dialog';
import { MapPin, Clock, Globe, Star, Share2, Heart, MessageCircle, Utensils, ShoppingBag, BadgeCheck, ExternalLink, Facebook, Instagram, Twitter, Linkedin, Plus } from 'lucide-react';
import BlurImage from '@/Components/Molecules/BlurImage';
import ProductOptionsModal from '@/Components/Organisms/ProductOptionsModal';
import FloatingCart from '@/Components/Organisms/FloatingCart';
import useCartStore from '@/Stores/useCartStore';
import { cn } from '@/lib/utils';

export default function ProBusinessPage({ tenant, products, isOpen, openingHours }) {
    const isRestaurant = tenant?.category?.name === 'Restaurantes' || tenant?.category?.name === 'Comida';
    const safeProducts = Array.isArray(products) ? products : [];
    const primaryColor = tenant?.primary_color || '#000000';

    // Estado para el modal de opciones
    const [selectedProduct, setSelectedProduct] = useState(null);
    const [isOptionsModalOpen, setIsOptionsModalOpen] = useState(false);

    // Inicializar carrito con info del tenant
    const initializeCart = useCartStore(state => state.initializeCart);
    const addItem = useCartStore(state => state.addItem);

    useEffect(() => {
        if (tenant) {
            initializeCart({
                id: tenant.id,
                slug: tenant.slug,
                name: tenant.name,
                whatsapp: tenant.whatsapp_number,
            });
        }
    }, [tenant?.id]);

    // Manejar click en producto
    const handleProductClick = (product) => {
        const optionGroups = product.option_groups || product.optionGroups || [];

        if (optionGroups.length > 0) {
            // Tiene opciones, abrir modal
            setSelectedProduct(product);
            setIsOptionsModalOpen(true);
        } else {
            // Sin opciones, agregar directo
            addItem(product, [], 1, '');
        }
    };

    if (!tenant) return null;

    // Helper to render social icons
    const renderSocialIcon = (platform, url) => {
        if (!url) return null;
        const icons = {
            facebook: Facebook,
            instagram: Instagram,
            twitter: Twitter,
            linkedin: Linkedin,
            tiktok: Share2 // Fallback or specific icon
        };
        const Icon = icons[platform.toLowerCase()] || Globe;

        return (
            <a key={platform} href={url} target="_blank" rel="noopener noreferrer" className="p-2 bg-white/10 hover:bg-white/20 rounded-full transition-colors text-white">
                <Icon className="w-5 h-5" />
            </a>
        );
    };

    return (
        <div className="min-h-screen bg-background font-sans pb-20" style={{ '--primary-color': primaryColor }}>
            {/* Hero Section - Immersive */}
            <div className="relative h-[40vh] md:h-[50vh] w-full overflow-hidden bg-slate-900">
                {tenant.banner_path ? (
                    /*
                    <BlurImage
                        src={`/storage/${tenant.banner_path}`}
                        blurhash={tenant.blur_hash}
                        alt="Banner"
                        className="w-full h-full object-cover"
                    />
                    */
                    <div className="w-full h-full bg-gray-800"></div>
                ) : (
                    <div className="w-full h-full bg-slate-900 flex items-center justify-center" style={{ backgroundColor: primaryColor }}>
                        <span className="text-white/20 font-sans text-5xl md:text-9xl font-bold tracking-widest uppercase select-none">
                            {tenant.name}
                        </span>
                    </div>
                )}
                <div className="absolute inset-0 bg-gradient-to-t from-black/90 via-black/20 to-transparent"></div>

                {/* Branding Overlay */}
                <div className="absolute bottom-0 left-0 w-full p-6 md:p-12 z-20">
                    <div className="max-w-7xl mx-auto flex flex-col md:flex-row items-end md:items-center gap-6">
                        <div className="w-24 h-24 md:w-32 md:h-32 bg-white rounded-2xl border-4 border-white shadow-2xl overflow-hidden flex-shrink-0">
                            {tenant.logo_path ? (
                                /*
                                <BlurImage
                                    src={`/storage/${tenant.logo_path}`}
                                    blurhash={tenant.blur_hash}
                                    alt="Logo"
                                    className="w-full h-full object-cover"
                                />
                                */
                                <div className="w-full h-full bg-gray-200"></div>
                            ) : (
                                <div className="w-full h-full flex items-center justify-center" style={{ backgroundColor: primaryColor }}>
                                    <span className="text-3xl font-bold text-white">{tenant.name?.substring(0, 2).toUpperCase()}</span>
                                </div>
                            )}
                        </div>
                        <div className="flex-1 mb-2">
                            <h1 className="text-3xl md:text-5xl font-bold text-white mb-2 flex items-center gap-3 shadow-black drop-shadow-lg">
                                {tenant.name}
                                <BadgeCheck className="w-8 h-8 text-blue-400 fill-blue-900/50" />
                            </h1>
                            <div className="flex flex-wrap items-center gap-3 text-white/90">
                                <Badge className="bg-white/20 hover:bg-white/30 text-white border-none backdrop-blur-md">
                                    {tenant.category?.name || 'General'}
                                </Badge>
                                <div className="flex items-center gap-1">
                                    <Star className="w-4 h-4 fill-amber-400 text-amber-400" />
                                    <span className="font-bold">4.8</span>
                                    <span className="opacity-70">(12 reseñas)</span>
                                </div>
                                <span className="hidden md:inline text-white/40">•</span>
                                <div className="flex items-center gap-1">
                                    <MapPin className="w-4 h-4 opacity-70" />
                                    <span className="opacity-90">{tenant.city?.name || 'Honduras'}</span>
                                </div>
                            </div>

                            {/* Social Links - DEBUGGING COMMENTED OUT
                            {tenant.social_links && typeof tenant.social_links === 'object' && Object.keys(tenant.social_links).length > 0 && (
                                <div className="flex gap-2 mt-4">
                                    {Object.entries(tenant.social_links).map(([platform, url]) => renderSocialIcon(platform, url))}
                                </div>
                            )}
                            */}
                        </div>
                    </div>
                </div>
            </div>

            {/* Sticky Action Bar */}
            <div className="sticky top-0 z-40 bg-background/80 backdrop-blur-md border-b border-border shadow-sm">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-16">
                        <Tabs defaultValue="products" className="w-full">
                            <TabsList className="h-16 bg-transparent p-0 w-full justify-start gap-6">
                                <TabsTrigger
                                    value="products"
                                    className="h-full rounded-none border-b-2 border-transparent data-[state=active]:border-[var(--primary-color)] data-[state=active]:text-[var(--primary-color)] px-0 font-medium text-base bg-transparent"
                                    style={{ '--primary-color': primaryColor }}
                                >
                                    {isRestaurant ? 'Menú' : 'Catálogo'}
                                </TabsTrigger>
                                <TabsTrigger
                                    value="info"
                                    className="h-full rounded-none border-b-2 border-transparent data-[state=active]:border-[var(--primary-color)] data-[state=active]:text-[var(--primary-color)] px-0 font-medium text-base bg-transparent"
                                    style={{ '--primary-color': primaryColor }}
                                >
                                    Información
                                </TabsTrigger>
                                <TabsTrigger
                                    value="reviews"
                                    className="h-full rounded-none border-b-2 border-transparent data-[state=active]:border-[var(--primary-color)] data-[state=active]:text-[var(--primary-color)] px-0 font-medium text-base bg-transparent"
                                    style={{ '--primary-color': primaryColor }}
                                >
                                    Reseñas
                                </TabsTrigger>
                            </TabsList>
                        </Tabs>
                    </div>
                </div>
            </div>

            {/* Main Content */}
            <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                <Tabs defaultValue="products" className="w-full">
                    <TabsContent value="products" className="mt-0 space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        {safeProducts.length > 0 ? (
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {safeProducts.map((product) => {
                                    const optionGroups = product.option_groups || product.optionGroups || [];
                                    const hasOptions = optionGroups.length > 0;

                                    return (
                                        <div
                                            key={product.id}
                                            onClick={() => handleProductClick(product)}
                                            className="group bg-card hover:bg-accent/5 rounded-xl border border-border hover:border-[var(--primary-color)]/30 transition-all cursor-pointer flex overflow-hidden h-32 md:h-40"
                                            style={{ '--primary-color': primaryColor }}
                                        >
                                            {/* Imagen */}
                                            <div className="w-32 md:w-40 h-full shrink-0 bg-muted relative overflow-hidden">
                                                {product.image_path ? (
                                                    <img
                                                        src={`/storage/${product.image_path}`}
                                                        alt={product.name}
                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                    />
                                                ) : (
                                                    <div className="w-full h-full flex items-center justify-center bg-muted">
                                                        <ShoppingBag className="w-8 h-8 text-muted-foreground/20" />
                                                    </div>
                                                )}
                                            </div>

                                            {/* Info */}
                                            <div className="flex-1 p-4 flex flex-col justify-between">
                                                <div>
                                                    <h3 className="font-bold text-foreground line-clamp-1 group-hover:text-[var(--primary-color)] transition-colors" style={{ '--primary-color': primaryColor }}>
                                                        {product.name}
                                                    </h3>
                                                    <p className="text-muted-foreground text-sm line-clamp-2 mt-1">
                                                        {product.description}
                                                    </p>
                                                </div>
                                                <div className="flex items-center justify-between mt-2">
                                                    <div>
                                                        <span className="font-bold text-lg">
                                                            {hasOptions ? 'Desde ' : ''}L. {product.price}
                                                        </span>
                                                        {hasOptions && (
                                                            <span className="text-xs text-muted-foreground ml-1">
                                                                ({optionGroups.length} opciones)
                                                            </span>
                                                        )}
                                                    </div>
                                                    <Button
                                                        size="sm"
                                                        className="rounded-full h-8 w-8 p-0"
                                                        style={{ backgroundColor: primaryColor }}
                                                    >
                                                        <Plus className="w-4 h-4 text-white" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <div className="text-center py-20">
                                <ShoppingBag className="w-16 h-16 text-muted-foreground/20 mx-auto mb-4" />
                                <h3 className="text-lg font-medium text-foreground">Catálogo vacío</h3>
                                <p className="text-muted-foreground">Este negocio aún no ha publicado productos.</p>
                            </div>
                        )}
                    </TabsContent>

                    <TabsContent value="info" className="mt-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div className="grid md:grid-cols-3 gap-8">
                            <div className="md:col-span-2 space-y-6">
                                <div className="bg-card border border-border rounded-xl p-6">
                                    <h3 className="font-bold text-lg mb-4">Sobre Nosotros</h3>
                                    <p className="text-muted-foreground leading-relaxed whitespace-pre-line">
                                        {tenant.description || 'Sin descripción disponible.'}
                                    </p>
                                </div>
                            </div>
                            <div className="space-y-6">
                                <div className="bg-card border border-border rounded-xl p-6 space-y-4">
                                    <div className="flex items-start gap-3">
                                        <MapPin className="w-5 h-5 mt-0.5" style={{ color: primaryColor }} />
                                        <div>
                                            <h4 className="font-medium">Ubicación</h4>
                                            <p className="text-sm text-muted-foreground">{tenant.address || 'No especificada'}</p>
                                        </div>
                                    </div>
                                    <div className="flex items-start gap-3">
                                        <Clock className="w-5 h-5 mt-0.5" style={{ color: primaryColor }} />
                                        <div>
                                            <h4 className="font-medium">Horario</h4>
                                            <p className="text-sm text-muted-foreground">
                                                {isOpen ? <span className="text-green-600 font-medium">Abierto ahora</span> : <span className="text-red-500 font-medium">Cerrado</span>}
                                            </p>
                                        </div>
                                    </div>
                                    {tenant.website && (
                                        <div className="flex items-start gap-3">
                                            <Globe className="w-5 h-5 mt-0.5" style={{ color: primaryColor }} />
                                            <div>
                                                <h4 className="font-medium">Sitio Web</h4>
                                                <a href={tenant.website} target="_blank" rel="noopener noreferrer" className="text-sm text-blue-600 hover:underline flex items-center gap-1">
                                                    Visitar <ExternalLink className="w-3 h-3" />
                                                </a>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </TabsContent>

                    <TabsContent value="reviews" className="mt-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                        <div className="text-center py-12 bg-muted/30 rounded-xl border border-dashed border-border">
                            <MessageCircle className="w-12 h-12 text-muted-foreground/20 mx-auto mb-4" />
                            <h3 className="text-lg font-medium">Reseñas</h3>
                            <p className="text-muted-foreground">Próximamente disponible.</p>
                        </div>
                    </TabsContent>
                </Tabs>
            </main>
            {/* Carrito Flotante */}
            <FloatingCart primaryColor={primaryColor} />

            {/* Modal de Opciones */}
            <ProductOptionsModal
                product={selectedProduct}
                isOpen={isOptionsModalOpen}
                onClose={() => {
                    setIsOptionsModalOpen(false);
                    setSelectedProduct(null);
                }}
                tenantName={tenant.name}
                primaryColor={primaryColor}
            />
        </div>
    );
}
