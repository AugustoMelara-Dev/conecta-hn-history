import { useState } from 'react';
import { Head, Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Card } from '@/Components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/Components/ui/dialog';
import { MapPin, Phone, Clock, Globe, Star, Share2, Heart, MessageCircle, Utensils, ShoppingBag, X } from 'lucide-react';
import TenantLayout from '@/Layouts/TenantLayout';
import BlurImage from '@/Components/Molecules/BlurImage';

export default function TenantShow({ tenant, products, isFollowing, followersCount, seoStructuredData, isOpen, openingHours }) {
    const isPro = tenant.is_pro;
    const isRestaurant = tenant.category?.name === 'Restaurantes' || tenant.category?.name === 'Comida';
    const [selectedProduct, setSelectedProduct] = useState(null);

    // Downgrade Logic: Limit products for Free tier
    const displayProducts = isPro ? products : products.slice(0, 10);

    // Branding Colors (Mock)
    const brandColor = isPro ? (tenant.brand_color || 'bg-black') : 'bg-black';

    return (
        <TenantLayout tenant={tenant}>
            <Head>
                <title>{tenant.name} - Conecta HN</title>
                <meta name="description" content={tenant.description} />
                {seoStructuredData && (
                    <script type="application/ld+json">
                        {JSON.stringify(seoStructuredData)}
                    </script>
                )}
            </Head>

            <div className="min-h-screen bg-white font-sans text-slate-900 selection:bg-slate-900 selection:text-white">
                {/* Navbar (Simplified for Microsite) */}
                <nav className="sticky top-0 z-50 bg-white/80 backdrop-blur-md border-b border-slate-100">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                        <Link href={route('directory.index')} className="flex items-center gap-2 text-slate-600 hover:text-slate-900 transition-colors">
                            <span className="text-sm font-medium">← Volver al Directorio</span>
                        </Link>
                        <Link href="/" className="flex items-center gap-2">
                            <div className="w-6 h-6 bg-black rounded flex items-center justify-center">
                                <span className="text-white font-bold text-xs">C</span>
                            </div>
                            <span className="font-bold text-lg tracking-tight">Conecta HN</span>
                        </Link>
                    </div>
                </nav>

                {/* Banner Section with Null Safety - Pilar 7: BlurImage */}
                <div className="relative h-64 md:h-80 lg:h-96 w-full overflow-hidden bg-slate-100">
                    {tenant.banner_path ? (
                        <BlurImage
                            src={`/storage/${tenant.banner_path}`}
                            blurhash={tenant.blur_hash}
                            alt="Banner"
                            className="w-full h-full object-cover"
                        />
                    ) : (
                        <div className="w-full h-full bg-[radial-gradient(ellipse_at_center,_var(--tw-gradient-stops))] from-slate-800 via-slate-900 to-black flex items-center justify-center">
                            <span className="text-white/5 font-sans text-5xl md:text-7xl font-bold tracking-widest uppercase select-none">
                                {tenant.name}
                            </span>
                        </div>
                    )}
                    <div className="absolute inset-0 bg-black/10"></div>
                </div>

                <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 -mt-20 relative z-10 pb-20">
                    <div className="flex flex-col lg:flex-row gap-8">
                        {/* Sidebar Info */}
                        <aside className="w-full lg:w-1/3 space-y-6">
                            <div className="bg-white rounded-2xl shadow-xl shadow-slate-200/50 border border-slate-100 p-6 md:p-8">
                                <div className="flex flex-col items-center text-center">
                                    <div className="w-32 h-32 bg-white rounded-full border-4 border-white shadow-lg -mt-20 mb-4 overflow-hidden flex items-center justify-center bg-slate-50">
                                        {tenant.logo_path ? (
                                            <BlurImage
                                                src={`/storage/${tenant.logo_path}`}
                                                blurhash={tenant.blur_hash}
                                                alt="Logo"
                                                className="w-full h-full object-cover"
                                            />
                                        ) : (
                                            <span className="text-3xl font-bold text-slate-900">{tenant.name.substring(0, 2).toUpperCase()}</span>
                                        )}
                                    </div>

                                    <h1 className="text-2xl md:text-3xl font-bold text-slate-900 mb-2 leading-tight">{tenant.name}</h1>

                                    <div className="flex flex-wrap justify-center gap-2 mb-4">
                                        <Badge variant="secondary" className="bg-slate-100 text-slate-700 hover:bg-slate-200">
                                            {tenant.category?.name || 'General'}
                                        </Badge>
                                        {isOpen ? (
                                            <Badge className="bg-green-100 text-green-700 hover:bg-green-200 border-none shadow-none">Abierto</Badge>
                                        ) : (
                                            <Badge className="bg-red-100 text-red-700 hover:bg-red-200 border-none shadow-none">Cerrado</Badge>
                                        )}
                                        {isPro && <Badge className="bg-black text-white border-none">Verificado</Badge>}
                                    </div>

                                    <div className="flex items-center justify-center gap-1 mb-6">
                                        {[1, 2, 3, 4, 5].map((star) => (
                                            <Star key={star} className={`w-5 h-5 ${star <= 4 ? 'fill-yellow-400 text-yellow-400' : 'text-slate-200'}`} />
                                        ))}
                                        <span className="text-sm text-slate-500 ml-2 font-medium">4.0 (12)</span>
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
                                        ) : !isPro ? (
                                            <div className="text-center py-3 bg-slate-50 rounded-lg border border-slate-100">
                                                <p className="text-xs text-slate-500 font-medium">Contacto directo disponible en Plan PRO</p>
                                            </div>
                                        ) : null}

                                        <div className="grid grid-cols-2 gap-3">
                                            <Button variant="outline" className="w-full rounded-full border-slate-200 hover:bg-slate-50">
                                                <Heart className="mr-2 h-4 w-4" /> Seguir
                                            </Button>
                                            <Button variant="outline" className="w-full rounded-full border-slate-200 hover:bg-slate-50">
                                                <Share2 className="mr-2 h-4 w-4" /> Compartir
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-8 space-y-5 pt-8 border-t border-slate-100">
                                    <div className="flex items-start gap-3 text-slate-600">
                                        <MapPin className="w-5 h-5 mt-0.5 text-slate-400 shrink-0" />
                                        <p className="text-sm leading-relaxed">{tenant.address || 'Dirección no disponible'}</p>
                                    </div>
                                    <div className="flex items-center gap-3 text-slate-600">
                                        <Clock className="w-5 h-5 text-slate-400 shrink-0" />
                                        <div className="text-sm">
                                            {openingHours ? (
                                                <p>{isOpen ? 'Abierto ahora' : 'Cerrado ahora'}</p>
                                            ) : (
                                                <p>Horario no disponible</p>
                                            )}
                                        </div>
                                    </div>
                                    {tenant.website && (
                                        <div className="flex items-center gap-3 text-slate-600">
                                            <Globe className="w-5 h-5 text-slate-400 shrink-0" />
                                            <a href={tenant.website} target="_blank" rel="noopener noreferrer" className="text-sm hover:underline hover:text-slate-900 truncate">
                                                {tenant.website.replace(/^https?:\/\//, '')}
                                            </a>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </aside>

                        {/* Main Content Tabs */}
                        <div className="flex-1">
                            <Tabs defaultValue="products" className="w-full">
                                <TabsList className="w-full justify-start bg-transparent border-b border-slate-200 rounded-none h-auto p-0 mb-8">
                                    <TabsTrigger
                                        value="products"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-black data-[state=active]:bg-transparent data-[state=active]:text-black px-6 py-3 text-base font-medium text-slate-500"
                                    >
                                        {isRestaurant ? 'Menú' : 'Catálogo'}
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="reviews"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-black data-[state=active]:bg-transparent data-[state=active]:text-black px-6 py-3 text-base font-medium text-slate-500"
                                    >
                                        Reseñas
                                    </TabsTrigger>
                                    <TabsTrigger
                                        value="info"
                                        className="rounded-none border-b-2 border-transparent data-[state=active]:border-black data-[state=active]:bg-transparent data-[state=active]:text-black px-6 py-3 text-base font-medium text-slate-500"
                                    >
                                        Información
                                    </TabsTrigger>
                                </TabsList>

                                <TabsContent value="products" className="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    {!isPro && (
                                        <div className="bg-slate-50 border border-slate-200 rounded-lg p-4 mb-6 flex items-center justify-between">
                                            <p className="text-sm text-slate-600">Mostrando 10 productos destacados.</p>
                                            <Badge variant="outline" className="bg-white border-slate-200">Plan Gratuito</Badge>
                                        </div>
                                    )}

                                    {displayProducts.length > 0 ? (
                                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                            {displayProducts.map((product) => (
                                                <Dialog key={product.id}>
                                                    <DialogTrigger asChild>
                                                        <div className="group bg-white rounded-xl border border-slate-100 hover:border-slate-300 hover:shadow-lg transition-all overflow-hidden cursor-pointer">
                                                            <div className="aspect-square bg-slate-100 relative overflow-hidden">
                                                                {product.image_path ? (
                                                                    <BlurImage
                                                                        src={`/storage/${product.image_path}`}
                                                                        blurhash={product.blur_hash}
                                                                        alt={product.name}
                                                                        className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                                    />
                                                                ) : (
                                                                    <div className="w-full h-full flex items-center justify-center bg-slate-50">
                                                                        {isRestaurant ? <Utensils className="w-10 h-10 text-slate-300" /> : <ShoppingBag className="w-10 h-10 text-slate-300" />}
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <div className="p-4">
                                                                <h3 className="font-bold text-slate-900 truncate">{product.name}</h3>
                                                                <p className="text-slate-500 text-sm mt-1 truncate">{product.description}</p>
                                                                <div className="flex items-center justify-between mt-4">
                                                                    <span className="font-bold text-slate-900">L. {product.price}</span>
                                                                    <Button size="sm" className="rounded-full bg-black text-white hover:bg-slate-800 h-8 w-8 p-0">
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
                                                            <div className="aspect-video bg-slate-100 rounded-lg overflow-hidden">
                                                                {product.image ? (
                                                                    <img src={`/storage/${product.image}`} className="w-full h-full object-cover" />
                                                                ) : (
                                                                    <div className="w-full h-full flex items-center justify-center bg-slate-50">
                                                                        <ShoppingBag className="w-12 h-12 text-slate-300" />
                                                                    </div>
                                                                )}
                                                            </div>
                                                            <p className="text-slate-600 leading-relaxed">{product.description}</p>
                                                            <div className="flex items-center justify-between pt-4 border-t border-slate-100">
                                                                <span className="text-2xl font-bold text-slate-900">L. {product.price}</span>
                                                                {isPro && tenant.whatsapp_number ? (
                                                                    <Button className="bg-black text-white hover:bg-slate-800 rounded-full">
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
                                        <div className="text-center py-16 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                            <p className="text-slate-500">Este negocio aún no ha publicado productos.</p>
                                        </div>
                                    )}
                                </TabsContent>

                                <TabsContent value="reviews">
                                    <div className="text-center py-16 bg-slate-50 rounded-xl border-2 border-dashed border-slate-200">
                                        <p className="text-slate-500">Las reseñas estarán disponibles pronto.</p>
                                    </div>
                                </TabsContent>

                                <TabsContent value="info">
                                    <Card className="border-slate-200 shadow-sm">
                                        <div className="p-6 space-y-4">
                                            <h3 className="font-bold text-lg text-slate-900">Sobre Nosotros</h3>
                                            <p className="text-slate-600 leading-relaxed">
                                                {tenant.description || 'Sin descripción disponible.'}
                                            </p>
                                        </div>
                                    </Card>
                                </TabsContent>
                            </Tabs>
                        </div>
                    </div>
                </main>
            </div>
        </TenantLayout>
    );
}
