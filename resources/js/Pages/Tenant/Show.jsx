import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/Components/ui/dialog';
import { MapPin, Phone, Clock, Globe, Star, Share2, Heart, MessageCircle, Utensils, ShoppingBag, X, BadgeCheck, ExternalLink } from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import BlurImage from '@/Components/Molecules/BlurImage';
import { cn } from '@/lib/utils';

export default function TenantShow({ tenant, products, isFollowing, followersCount, seoStructuredData, isOpen, openingHours }) {
    const isPro = tenant.is_pro;
    const isRestaurant = tenant.category?.name === 'Restaurantes' || tenant.category?.name === 'Comida';

    // Null Safety for Products
    const safeProducts = Array.isArray(products) ? products : [];

    // Downgrade Logic: Limit products for Free tier
    const displayProducts = isPro ? safeProducts : safeProducts.slice(0, 10);

    return (
        <TenantLayout tenant={tenant}>
            <Head>
                <title>{tenant.name} - Conecta HN</title>
                <meta name="description" content={tenant.description || `Descubre ${tenant.name} en Conecta HN.`} />
                {seoStructuredData && (
                    <script type="application/ld+json">
                        {JSON.stringify(seoStructuredData)}
                    </script>
                )}
            </Head>

            <div className="min-h-screen bg-background font-sans">
                {/* Navbar (Simplified for Microsite) */}
                <nav className="sticky top-0 z-50 bg-background/80 backdrop-blur-md border-b border-border">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <Link href={route('directory.index')} className="flex items-center gap-2 text-muted-foreground hover:text-foreground transition-colors">
                            <span className="text-sm font-medium">← Volver al Directorio</span>
                        </Link>
                        <Link href="/" className="flex items-center gap-2">
                            <div className="w-6 h-6 bg-primary rounded flex items-center justify-center">
                                <span className="text-primary-foreground font-bold text-xs">C</span>
                            </div>
                            <span className="font-bold text-lg tracking-tight hidden sm:inline">Conecta HN</span>
                        </Link>
                    </div>
                </nav>

                {/* Hero Banner */}
                <div className="relative h-64 md:h-80 lg:h-96 w-full overflow-hidden bg-muted">
                    {tenant.banner_path ? (
                        <BlurImage
                            src={`/storage/${tenant.banner_path}`}
                            blurhash={tenant.blur_hash}
                            alt="Banner"
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full bg-gradient-to-r from-slate-100 to-slate-300 flex items-center justify-center">
                            <span className="text-muted-foreground/10 font-sans text-5xl md:text-7xl font-bold tracking-widest uppercase select-none">
                                {tenant.name}
                            </span>
                        </div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-background/80 to-transparent"></div>
                </div>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20 relative z-10 pb-20">
                    <div className="flex flex-col lg:flex-row gap-8">
                        {/* Sidebar Info */}
                        <aside className="w-full lg:w-1/3 space-y-6">
                            <div className="bg-card rounded-2xl shadow-lg border border-border p-6 md:p-8">
                                <div className="flex flex-col items-center text-center">
                                    <div className="w-32 h-32 bg-background rounded-full border-4 border-background shadow-lg -mt-24 mb-4 overflow-hidden flex items-center justify-center">
                                        {tenant.logo_path ? (
                                            <BlurImage
                                                src={`/storage/${tenant.logo_path}`}
                                                blurhash={tenant.blur_hash}
                                                alt="Logo"
                                                className="w-full h-full object-cover"
                                            />
                                        ) : (
                                            <div className="w-full h-full bg-primary/5 flex items-center justify-center">
                                                <span className="text-3xl font-bold text-primary">{tenant.name.substring(0, 2).toUpperCase()}</span>
                                            </div>
                                        )}
                                    </div>

                                    <h1 className="text-2xl md:text-3xl font-bold text-foreground mb-2 leading-tight flex items-center gap-2 justify-center">
                                        {tenant.name}
                                        {isPro && <BadgeCheck className="w-6 h-6 text-blue-500 fill-blue-500/10" />}
                                    </h1>

                                    <div className="flex flex-wrap justify-center gap-2 mb-4">
                                        <Badge variant="secondary" className="bg-muted text-muted-foreground hover:bg-muted/80">
                                            {tenant.category?.name || 'General'}
                                        </Badge>
                                        {isOpen ? (
                                            <Badge className="bg-green-500/10 text-green-700 hover:bg-green-500/20 border-none shadow-none">Abierto</Badge>
                                        ) : (
                                            <Badge className="bg-red-500/10 text-red-700 hover:bg-red-500/20 border-none shadow-none">Cerrado</Badge>
                                        )}
                                    </div>

                                    <div className="flex items-center justify-center gap-1 mb-6">
                                        {[1, 2, 3, 4, 5].map((star) => (
                                            <Star key={star} className={`w-5 h-5 ${star <= 4 ? 'fill-primary text-primary' : 'text-muted'}`} />
                                        ))}
                                        <span className="text-sm text-muted-foreground ml-2 font-medium">4.8 (12)</span>
                                    </div>

                                    <div className="w-full space-y-3">
                                        {/* WhatsApp Button: Only for PRO with number */}
                                        {isPro && tenant.whatsapp_number ? (
                                            <a href={`https://wa.me/${tenant.whatsapp_number.replace(/\D/g, '')}`} target="_blank" rel="noopener noreferrer" className="block w-full">
                                                <Button className="w-full bg-green-600 hover:bg-green-700 text-white rounded-full h-12 text-base shadow-lg shadow-green-600/20 transition-all hover:scale-[1.02]">
                                                    <MessageCircle className="mr-2 h-5 w-5" />
                                                    WhatsApp
                                                </Button>
                                            </a>
                                        ) : null}

                                        <div className="grid grid-cols-2 gap-3">
                                            <Button variant="outline" className="w-full rounded-full">
                                                <Heart className="mr-2 h-4 w-4" /> Seguir
                                            </Button>
                                            <Button variant="outline" className="w-full rounded-full">
                                                <Share2 className="mr-2 h-4 w-4" /> Compartir
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-8 space-y-5 pt-8 border-t border-border">
                                    <div className="flex items-start gap-3 text-muted-foreground">
                                        <MapPin className="w-5 h-5 mt-0.5 shrink-0" />
                                        <p className="text-sm leading-relaxed">{tenant.address || 'Dirección no disponible'}</p>
                                    </div>
                                    <div className="flex items-center gap-3 text-muted-foreground">
                                        <Clock className="w-5 h-5 shrink-0" />
                                        <div className="text-sm">
                                            {openingHours ? (
                                                <p>{isOpen ? 'Abierto ahora' : 'Cerrado ahora'}</p>
                                            ) : (
                                                <p>Horario no disponible</p>
                                            )}
                                        </div>
                                    </div>
                                    {tenant.website && (
                                        <div className="flex items-center gap-3 text-muted-foreground">
                                            <Globe className="w-5 h-5 shrink-0" />
                                            <a href={tenant.website} target="_blank" rel="noopener noreferrer" className="text-sm hover:underline hover:text-foreground truncate flex items-center gap-1">
                                                Sitio Web <ExternalLink className="w-3 h-3" />
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </aside>

                        {/* Main Content Tabs */}
                        <div className="flex-1">
                            <Tabs defaultValue="products" className="w-full">
                                <TabsList className="w-full justify-start bg-transparent border-b border-border rounded-none h-auto p-0 mb-8">
                                    <TabsTrigger
                                        value="products"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground px-6 py-3 text-base font-medium text-muted-foreground"
                                    >
                                        {isRestaurant ? 'Menú' : 'Catálogo'}
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="info"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground px-6 py-3 text-base font-medium text-muted-foreground"
                                    >
                                        Información
                                    </TabsTrigger>
                                    {isPro && (
                                        <TabsTrigger
                                            value="reviews"
                                            className="rounded-none border-b-2 border-transparent data-[state=active]:border-primary data-[state=active]:bg-transparent data-[state=active]:text-foreground px-6 py-3 text-base font-medium text-muted-foreground"
                                        >
                                            Reseñas
                                        </TabsTrigger>
                                    )}
                                </TabsList>

                                <TabsContent value="products" className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    {!isPro && (
                                        <div className="bg-muted/50 border border-border rounded-lg p-4 mb-6 flex items-center justify-between">
                                            <p className="text-sm text-muted-foreground">Mostrando selección de productos.</p>
                                        </div>
                                    )}

                                    {displayProducts.length > 0 ? (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                            {displayProducts.map((product) => (
                                                <Dialog key={product.id}>
                                                    <DialogTrigger asChild>
                                                        <div className="group bg-card rounded-xl border border-border hover:border-primary/50 hover:shadow-lg transition-all overflow-hidden cursor-pointer">
                                                            <div className="aspect-square bg-muted relative overflow-hidden">
                                                                {product.image_path ? (
                                                                    <BlurImage
                                                                        src={`/storage/${product.image_path}`}
                                                                        blurhash={product.blur_hash}
                                                                        alt={product.name}
                                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                                    />
                                                                ) : (
                                                                    <div className="w-full h-full flex items-center justify-center bg-muted">
                                                                        {isRestaurant ? <Utensils className="w-10 h-10 text-muted-foreground/20" /> : <ShoppingBag className="w-10 h-10 text-muted-foreground/20" />}
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="p-4">
                                                                <h3 className="font-bold text-foreground truncate">{product.name}</h3>
                                                                <p className="text-muted-foreground text-sm mt-1 truncate">{product.description}</p>
                                                                <div className="flex items-center justify-between mt-4">
                                                                    <span className="font-bold text-foreground">L. {product.price}</span>
                                                                    <Button size="sm" className="rounded-full h-8 w-8 p-0">
                                                                        +
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </DialogTrigger>
                                                    <DialogContent className="sm:max-w-md">
                                                        <DialogHeader>
                                                            <DialogTitle>{product.name}</DialogTitle>
                                                            <DialogDescription>
                                                                {tenant.name}
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <div className="space-y-4">
                                                            <div className="aspect-video bg-muted rounded-lg overflow-hidden">
                                                                {product.image_path ? (
                                                                    <img src={`/storage/${product.image_path}`} className="w-full h-full object-cover" />
                                                                ) : (
                                                                    <div className="w-full h-full flex items-center justify-center bg-muted">
                                                                        <ShoppingBag className="w-12 h-12 text-muted-foreground/20" />
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <p className="text-muted-foreground leading-relaxed">{product.description}</p>
                                                            <div className="flex items-center justify-between pt-4 border-t border-border">
                                                                <span className="text-2xl font-bold text-foreground">L. {product.price}</span>
                                                                {isPro && tenant.whatsapp_number ? (
                                                                    <Button className="rounded-full">
                                                                        Pedir por WhatsApp
                                                                    </Button>
                                                                ) : (
                                                                    <Button disabled variant="outline" className="rounded-full">
                                                                        Solo en Tienda
                                                                    </Button>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </DialogContent>
                                                </Dialog>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-16 bg-muted/30 rounded-xl border-2 border-dashed border-border">
                                            <p className="text-muted-foreground">Este negocio aún no ha publicado productos.</p>
                                        </div>
                                    )}
                                </TabsContent>

                                <TabsContent value="info">
                                    <Card className="border-border shadow-sm">
                                        <div className="p-6 space-y-4">
                                            <h3 className="font-bold text-lg text-foreground">Sobre Nosotros</h3>
                                            <p className="text-muted-foreground leading-relaxed">
                                                {tenant.description || 'Sin descripción disponible.'}
                                            </p>
                                        </div>
                                    </Card>
                                </TabsContent>

                                {isPro && (
                                    <TabsContent value="reviews">
                                        {tenant.reviews?.length > 0 ? (
                                            <div className="space-y-6">
                                                {tenant.reviews.map((review) => (
                                                    <div key={review.id} className="bg-card border border-border rounded-xl p-4 shadow-sm">
                                                        <div className="flex items-center justify-between mb-2">
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                                                    <span className="text-primary font-bold text-xs">
                                                                        {review.user?.name?.charAt(0) || 'U'}
                                                                    </span>
                                                                </div>
                                                                <span className="font-medium text-sm">{review.user?.name || 'Usuario'}</span>
                                                            </div>
                                                            <div className="flex items-center gap-1">
                                                                <Star className="w-4 h-4 fill-amber-500 text-amber-500" />
                                                                <span className="text-sm font-bold">{review.rating}</span>
                                                            </div>
                                                        </div>
                                                        <p className="text-muted-foreground text-sm leading-relaxed">{review.comment}</p>
                                                        <p className="text-xs text-muted-foreground mt-2">
                                                            {new Date(review.created_at).toLocaleDateString()}
                                                        </p>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="text-center py-16 bg-muted/30 rounded-xl border-2 border-dashed border-border">
                                                <MessageCircle className="w-10 h-10 text-muted-foreground/30 mx-auto mb-3" />
                                                <p className="text-muted-foreground">Este negocio aún no tiene reseñas.</p>
                                            </div>
                                        )}
                                    </TabsContent>
                                )}
                            </Tabs>
                        </div>
                    </div>
                </main>
            </div>
        </TenantLayout>
    );
}
