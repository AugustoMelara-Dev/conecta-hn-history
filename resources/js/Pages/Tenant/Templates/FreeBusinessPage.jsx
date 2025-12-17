import { Link } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogTrigger } from '@/Components/ui/dialog';
import { MapPin, Clock, Phone, ShoppingBag, Store } from 'lucide-react';
import BlurImage from '@/Components/Molecules/BlurImage';

export default function FreeBusinessPage({ tenant, products, isOpen }) {
    // Hard Limit: Only first 10 products
    const displayProducts = Array.isArray(products) ? products.slice(0, 10) : [];

    if (!tenant) return null;

    return (
        <div className="min-h-screen bg-slate-50 font-sans pb-24">
            {/* Basic Header */}
            <header className="bg-white border-b border-slate-200">
                <div className="h-32 bg-slate-100 relative overflow-hidden">
                    <div className="absolute inset-0 flex items-center justify-center opacity-10">
                        <Store className="w-32 h-32 text-slate-400" />
                    </div>
                </div>
                <div className="max-w-3xl mx-auto px-4 -mt-12 relative z-10 pb-6">
                    <div className="flex flex-col items-center text-center">
                        <div className="w-24 h-24 bg-white rounded-full border-4 border-white shadow-md overflow-hidden flex items-center justify-center mb-4">
                            {tenant.logo_path ? (
                                <BlurImage
                                    src={`/storage/${tenant.logo_path}`}
                                    blurhash={tenant.blur_hash}
                                    alt="Logo"
                                    className="w-full h-full object-cover"
                                />
                            ) : (
                                <div className="w-full h-full bg-slate-100 flex items-center justify-center">
                                    <span className="text-2xl font-bold text-slate-400">{tenant.name?.substring(0, 2).toUpperCase()}</span>
                                </div>
                            )}
                        </div>
                        <h1 className="text-2xl font-bold text-slate-900 mb-2">{tenant.name}</h1>
                        <div className="flex flex-wrap justify-center gap-2 mb-4">
                            <Badge variant="secondary" className="bg-slate-100 text-slate-600">
                                {tenant.category?.name || 'General'}
                            </Badge>
                            <div className="flex items-center gap-1 text-sm text-slate-500">
                                <MapPin className="w-4 h-4" />
                                <span>{tenant.city?.name || 'Honduras'}</span>
                            </div>
                        </div>
                        <div className="w-full max-w-xs">
                            <a href={`tel:${tenant.phone || ''}`}>
                                <Button variant="outline" className="w-full border-slate-300 text-slate-700 hover:bg-slate-50">
                                    <Phone className="w-4 h-4 mr-2" />
                                    Llamar al Negocio
                                </Button>
                            </a>
                        </div>
                    </div>
                </div>
            </header>

            {/* Simple Product List */}
            <main className="max-w-3xl mx-auto px-4 py-8">
                <h2 className="text-lg font-bold text-slate-900 mb-6 border-b border-slate-200 pb-2">
                    Productos Destacados
                </h2>

                {displayProducts.length > 0 ? (
                    <div className="space-y-4">
                        {displayProducts.map((product) => (
                            <Dialog key={product.id}>
                                <DialogTrigger asChild>
                                    <div className="bg-white rounded-lg border border-slate-200 p-4 flex gap-4 hover:shadow-md transition-shadow cursor-pointer">
                                        <div className="w-20 h-20 bg-slate-100 rounded-md shrink-0 overflow-hidden">
                                            {product.image_path ? (
                                                <BlurImage
                                                    src={`/storage/${product.image_path}`}
                                                    blurhash={product.blur_hash}
                                                    alt={product.name}
                                                    className="w-full h-full object-cover"
                                                />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center">
                                                    <ShoppingBag className="w-8 h-8 text-slate-300" />
                                                </div>
                                            )}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <h3 className="font-medium text-slate-900 truncate">{product.name}</h3>
                                            <p className="text-sm text-slate-500 line-clamp-2 mt-1">{product.description}</p>
                                            <p className="font-bold text-slate-900 mt-2">L. {product.price}</p>
                                        </div>
                                    </div>
                                </DialogTrigger>
                                <DialogContent className="sm:max-w-sm">
                                    <DialogHeader>
                                        <DialogTitle>{product.name}</DialogTitle>
                                        <DialogDescription>{tenant.name}</DialogDescription>
                                    </DialogHeader>
                                    <div className="space-y-4">
                                        <div className="aspect-square bg-slate-100 rounded-lg overflow-hidden">
                                            {product.image_path ? (
                                                <img src={`/storage/${product.image_path}`} className="w-full h-full object-cover" />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center">
                                                    <ShoppingBag className="w-16 h-16 text-slate-300" />
                                                </div>
                                            )}
                                        </div>
                                        <p className="text-slate-600">{product.description}</p>
                                        <div className="pt-4 border-t border-slate-100">
                                            <p className="text-2xl font-bold text-slate-900 text-center">L. {product.price}</p>
                                            <p className="text-xs text-center text-slate-400 mt-2">Visita el negocio para comprar.</p>
                                        </div>
                                    </div>
                                </DialogContent>
                            </Dialog>
                        ))}
                    </div>
                ) : (
                    <div className="text-center py-12 bg-white rounded-lg border border-dashed border-slate-200">
                        <p className="text-slate-500">No hay productos disponibles.</p>
                    </div>
                )}

                {/* Info Block */}
                <div className="mt-8 bg-white rounded-lg border border-slate-200 p-6">
                    <h3 className="font-bold text-slate-900 mb-2">Información</h3>
                    <p className="text-sm text-slate-600 mb-4">{tenant.description || 'Sin descripción.'}</p>
                    <div className="space-y-2 text-sm text-slate-600">
                        <div className="flex items-center gap-2">
                            <MapPin className="w-4 h-4" />
                            <span>{tenant.address || 'Dirección no disponible'}</span>
                        </div>
                        <div className="flex items-center gap-2">
                            <Clock className="w-4 h-4" />
                            <span>{isOpen ? 'Abierto' : 'Cerrado'}</span>
                        </div>
                    </div>
                </div>
            </main>

            {/* FOMO Footer */}
            <footer className="fixed bottom-0 left-0 w-full bg-white border-t border-slate-200 p-4 z-50 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.05)]">
                <div className="max-w-3xl mx-auto flex flex-col sm:flex-row items-center justify-between gap-4 text-center sm:text-left">
                    <div>
                        <p className="text-sm font-medium text-slate-900">⚡ Powered by Conecta HN - Crea tu tienda gratis.</p>
                        <p className="text-xs text-slate-500">Actualiza a PRO para eliminar esta marca.</p>
                    </div>
                    <Link href={route('register')}>
                        <Button size="sm" className="bg-slate-900 text-white hover:bg-slate-800">
                            Crear mi Tienda
                        </Button>
                    </Link>
                </div>
            </footer>
        </div>
    );
}
