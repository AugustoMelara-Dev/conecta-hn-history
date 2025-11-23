import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Heart, MapPin, Package, Star, User, Store } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';

export default function Profile({ auth, favorites = [], followedTenants = [] }) {
    return (
        <div className="min-h-screen bg-slate-50 font-sans text-slate-900">
            <Head title="Mi Perfil - Conecta HN" />

            {/* Navbar */}
            <nav className="bg-white border-b border-slate-200 sticky top-0 z-50">
                <div className="container mx-auto px-4 h-16 flex items-center justify-between">
                    <Link href="/" className="flex items-center gap-2 font-bold text-xl tracking-tight">
                        <div className="h-8 w-8 bg-primary rounded-lg flex items-center justify-center text-white">C</div>
                        Conecta HN
                    </Link>
                    <div className="flex items-center gap-4">
                        <Link href="/directorio">
                            <Button variant="ghost">Explorar Directorio</Button>
                        </Link>
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <div className="h-8 w-8 bg-slate-100 rounded-full flex items-center justify-center">
                                <User className="h-4 w-4 text-slate-600" />
                            </div>
                            <span>{auth.user.name}</span>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="container mx-auto px-4 py-8">
                <div className="max-w-5xl mx-auto">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold text-slate-900">Mi Perfil</h1>
                        <p className="text-slate-500">Gestiona tus favoritos y preferencias.</p>
                    </div>

                    <Tabs defaultValue="favorites" className="space-y-6">
                        <TabsList className="grid w-full grid-cols-2 max-w-[400px]">
                            <TabsTrigger value="favorites" className="flex gap-2">
                                <Heart className="w-4 h-4" /> Favoritos
                            </TabsTrigger>
                            <TabsTrigger value="following" className="flex gap-2">
                                <Store className="w-4 h-4" /> Siguiendo
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="favorites" className="space-y-6">
                            <div className="flex items-center gap-2 mb-4">
                                <Heart className="h-5 w-5 text-red-500 fill-current" />
                                <h2 className="text-xl font-semibold">Productos Favoritos</h2>
                            </div>

                            {favorites.length > 0 ? (
                                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {favorites.map((favorite) => (
                                        <Card key={favorite.id} className="overflow-hidden hover:shadow-md transition-shadow group">
                                            <div className="aspect-square bg-slate-100 relative overflow-hidden">
                                                <img
                                                    src={favorite.product.image_url || `https://source.unsplash.com/random/400x400?product&sig=${favorite.id}`}
                                                    alt={favorite.product.name}
                                                    className="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500"
                                                />
                                                <div className="absolute top-2 right-2">
                                                    <Button size="icon" variant="secondary" className="h-8 w-8 rounded-full bg-white/90 text-red-500 hover:bg-white hover:text-red-600">
                                                        <Heart className="h-4 w-4 fill-current" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <CardContent className="p-4">
                                                <div className="text-xs text-slate-500 mb-1">{favorite.product.category?.name || 'Categoría'}</div>
                                                <h3 className="font-bold truncate">{favorite.product.name}</h3>
                                                <div className="text-primary font-bold mt-1">
                                                    {new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(favorite.product.price)}
                                                </div>
                                            </CardContent>
                                            <CardFooter className="p-4 pt-0">
                                                <Link href={`/${favorite.product.tenant.slug}`} className="w-full">
                                                    <Button variant="outline" className="w-full text-xs">Ver Negocio</Button>
                                                </Link>
                                            </CardFooter>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                                        <div className="h-16 w-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                            <Heart className="h-8 w-8 text-slate-300" />
                                        </div>
                                        <h3 className="text-lg font-medium text-slate-900">Aún no tienes favoritos</h3>
                                        <p className="text-slate-500 max-w-sm mt-2 mb-6">
                                            Explora el directorio y guarda los productos que te interesen para encontrarlos fácilmente aquí.
                                        </p>
                                        <Link href="/directorio">
                                            <Button>Explorar Directorio</Button>
                                        </Link>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>

                        <TabsContent value="following" className="space-y-6">
                            <div className="flex items-center gap-2 mb-4">
                                <Store className="h-5 w-5 text-amber-500" />
                                <h2 className="text-xl font-semibold">Negocios que Sigo</h2>
                            </div>

                            {followedTenants.length > 0 ? (
                                <div className="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                    {followedTenants.map((tenant) => (
                                        <Card key={tenant.id} className="overflow-hidden hover:shadow-md transition-shadow group">
                                            <div className="h-32 bg-slate-900 relative">
                                                {/* Cover placeholder */}
                                                <div className="absolute inset-0 bg-gradient-to-b from-transparent to-slate-900/80"></div>
                                            </div>
                                            <div className="px-4 -mt-8 relative z-10 flex justify-between items-end">
                                                <div className="h-16 w-16 rounded-xl bg-white p-1 shadow-lg">
                                                    {tenant.logo_path ? (
                                                        <img src={`/storage/${tenant.logo_path}`} alt={tenant.name} className="w-full h-full object-cover rounded-lg" />
                                                    ) : (
                                                        <div className="w-full h-full bg-slate-100 rounded-lg flex items-center justify-center font-bold text-slate-400">
                                                            {tenant.name.charAt(0)}
                                                        </div>
                                                    )}
                                                </div>
                                                <Badge variant={tenant.is_pro ? "default" : "secondary"} className={tenant.is_pro ? "bg-amber-500 hover:bg-amber-600 text-slate-900" : ""}>
                                                    {tenant.is_pro ? "PRO" : "Gratis"}
                                                </Badge>
                                            </div>
                                            <CardContent className="p-4 pt-3">
                                                <h3 className="font-bold text-lg leading-tight mb-1">{tenant.name}</h3>
                                                <div className="flex items-center gap-1 text-sm text-slate-500 mb-3">
                                                    <MapPin className="w-3 h-3" />
                                                    {tenant.city || 'Honduras'}
                                                </div>
                                                <p className="text-sm text-slate-600 line-clamp-2 mb-4">
                                                    {tenant.description || 'Sin descripción.'}
                                                </p>
                                                <Link href={`/${tenant.slug}`} className="w-full">
                                                    <Button className="w-full">Visitar Perfil</Button>
                                                </Link>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <Card className="border-dashed">
                                    <CardContent className="flex flex-col items-center justify-center py-16 text-center">
                                        <div className="h-16 w-16 bg-slate-50 rounded-full flex items-center justify-center mb-4">
                                            <Store className="h-8 w-8 text-slate-300" />
                                        </div>
                                        <h3 className="text-lg font-medium text-slate-900">No sigues a ningún negocio</h3>
                                        <p className="text-slate-500 max-w-sm mt-2 mb-6">
                                            Sigue a tus negocios favoritos para recibir actualizaciones y ofertas exclusivas.
                                        </p>
                                        <Link href="/directorio">
                                            <Button>Explorar Directorio</Button>
                                        </Link>
                                    </CardContent>
                                </Card>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>
            </main>
        </div>
    );
}
