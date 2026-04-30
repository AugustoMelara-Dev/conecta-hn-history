import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { Heart, MapPin, Store, User } from 'lucide-react';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyIcon,
    EmptyTitle,
} from '@/Components/ui/empty';
import { Progress } from '@/Components/ui/progress';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';

export default function Profile({ auth, favorites = [], followedTenants = [], onboarding = null }) {
    return (
        <div className="min-h-screen bg-muted/30 font-sans text-foreground">
            <Head title="Mi Perfil - Vito Business OS" />

            <nav className="sticky top-0 z-50 border-b bg-background">
                <div className="container mx-auto flex h-16 items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2 text-xl font-bold tracking-tight">
                        <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">C</div>
                        Vito Business OS
                    </Link>
                    <div className="flex items-center gap-4">
                        <Button asChild variant="ghost">
                            <Link href="/directorio">Explorar Directorio</Link>
                        </Button>
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <div className="flex h-8 w-8 items-center justify-center rounded-full bg-muted">
                                <User className="h-4 w-4 text-muted-foreground" aria-hidden="true" />
                            </div>
                            <span>{auth.user.name}</span>
                        </div>
                    </div>
                </div>
            </nav>

            <main className="container mx-auto px-4 py-8">
                <div className="mx-auto max-w-5xl">
                    <div className="mb-8">
                        <h1 className="text-3xl font-bold tracking-tight">Mi Perfil</h1>
                        <p className="text-muted-foreground">Gestiona tus favoritos y preferencias.</p>
                    </div>

                    {onboarding && (
                        <Card className="mb-8">
                            <CardHeader>
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Activación de cuenta</p>
                                        <CardTitle className="text-xl">Checklist de usuario final</CardTitle>
                                    </div>
                                    <Badge>{onboarding.progress ?? 0}%</Badge>
                                </div>
                            </CardHeader>
                            <CardContent className="grid gap-4">
                                <Progress value={Math.max(6, onboarding.progress ?? 0)} aria-label="Progreso de activación de cuenta" />
                                <div className="grid gap-3">
                                    {(onboarding.milestones ?? []).map((milestone) => (
                                        <div key={milestone.key} className="flex items-start gap-3">
                                            <div
                                                className={`mt-1 h-5 w-5 rounded-full ${milestone.completed ? 'bg-primary' : 'bg-muted'}`}
                                                aria-hidden="true"
                                            />
                                            <div>
                                                <p className="font-medium">{milestone.title}</p>
                                                <p className="text-sm text-muted-foreground">{milestone.copy}</p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </CardContent>
                            <CardFooter>
                                <Button asChild>
                                    <Link href={onboarding.nextAction?.href || '/explore'} className="w-full sm:w-auto">
                                        {onboarding.nextAction?.title || 'Continuar'}
                                    </Link>
                                </Button>
                            </CardFooter>
                        </Card>
                    )}

                    <Tabs defaultValue="favorites" className="grid gap-6">
                        <TabsList className="grid w-full max-w-[400px] grid-cols-2" aria-label="Secciones del perfil">
                            <TabsTrigger value="favorites" className="flex gap-2">
                                <Heart className="h-4 w-4" aria-hidden="true" />
                                Favoritos
                            </TabsTrigger>
                            <TabsTrigger value="following" className="flex gap-2">
                                <Store className="h-4 w-4" aria-hidden="true" />
                                Siguiendo
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="favorites" className="grid gap-6">
                            <div className="flex items-center gap-2">
                                <Heart className="h-5 w-5 fill-current text-destructive" aria-hidden="true" />
                                <h2 className="text-xl font-semibold">Productos favoritos</h2>
                            </div>

                            {favorites.length > 0 ? (
                                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {favorites.map((favorite) => (
                                        <Card key={favorite.id} className="group overflow-hidden transition-shadow hover:shadow-md">
                                            <div className="relative aspect-square overflow-hidden bg-muted">
                                                <img
                                                    src={favorite.product.image_url || `https://source.unsplash.com/random/400x400?product&sig=${favorite.id}`}
                                                    alt={favorite.product.name}
                                                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                                                />
                                                <div className="absolute right-2 top-2">
                                                    <Button
                                                        type="button"
                                                        size="icon"
                                                        variant="secondary"
                                                        className="h-8 w-8 rounded-full"
                                                        aria-label={`Producto favorito: ${favorite.product.name}`}
                                                    >
                                                        <Heart className="h-4 w-4 fill-current text-destructive" aria-hidden="true" />
                                                    </Button>
                                                </div>
                                            </div>
                                            <CardContent className="p-4">
                                                <div className="mb-1 text-xs text-muted-foreground">{favorite.product.category?.name || 'Categoría'}</div>
                                                <h3 className="truncate font-bold">{favorite.product.name}</h3>
                                                <div className="mt-1 font-bold text-primary">
                                                    {new Intl.NumberFormat('es-HN', { style: 'currency', currency: 'HNL' }).format(favorite.product.price)}
                                                </div>
                                            </CardContent>
                                            <CardFooter className="p-4 pt-0">
                                                <Button asChild variant="outline" className="w-full text-xs">
                                                    <Link href={`/${favorite.product.tenant.slug}`}>Ver negocio</Link>
                                                </Button>
                                            </CardFooter>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <Empty aria-live="polite">
                                    <EmptyHeader>
                                        <EmptyIcon icon={Heart} />
                                        <EmptyTitle>Aún no tienes favoritos</EmptyTitle>
                                        <EmptyDescription>
                                            Explora el directorio y guarda los productos que te interesen para encontrarlos fácilmente aquí.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                    <EmptyContent>
                                        <Button asChild>
                                            <Link href="/directorio">Explorar Directorio</Link>
                                        </Button>
                                    </EmptyContent>
                                </Empty>
                            )}
                        </TabsContent>

                        <TabsContent value="following" className="grid gap-6">
                            <div className="flex items-center gap-2">
                                <Store className="h-5 w-5 text-primary" aria-hidden="true" />
                                <h2 className="text-xl font-semibold">Negocios que sigo</h2>
                            </div>

                            {followedTenants.length > 0 ? (
                                <div className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                    {followedTenants.map((tenant) => (
                                        <Card key={tenant.id} className="group overflow-hidden transition-shadow hover:shadow-md">
                                            <div className="relative h-32 bg-primary">
                                                <div className="absolute inset-0 bg-gradient-to-b from-transparent to-primary/80" aria-hidden="true" />
                                            </div>
                                            <div className="relative z-10 -mt-8 flex items-end justify-between px-4">
                                                <div className="h-16 w-16 rounded-xl bg-background p-1 shadow-lg">
                                                    {tenant.logo_path ? (
                                                        <img src={`/storage/${tenant.logo_path}`} alt={tenant.name} className="h-full w-full rounded-lg object-cover" />
                                                    ) : (
                                                        <div className="flex h-full w-full items-center justify-center rounded-lg bg-muted font-bold text-muted-foreground">
                                                            {tenant.name.charAt(0)}
                                                        </div>
                                                    )}
                                                </div>
                                                <Badge variant={tenant.is_pro ? 'default' : 'secondary'}>
                                                    {tenant.is_pro ? 'PRO' : 'Gratis'}
                                                </Badge>
                                            </div>
                                            <CardContent className="p-4 pt-3">
                                                <h3 className="mb-1 text-lg font-bold leading-tight">{tenant.name}</h3>
                                                <div className="mb-3 flex items-center gap-1 text-sm text-muted-foreground">
                                                    <MapPin className="h-3 w-3" aria-hidden="true" />
                                                    {tenant.city || 'Honduras'}
                                                </div>
                                                <p className="mb-4 line-clamp-2 text-sm text-muted-foreground">
                                                    {tenant.description || 'Sin descripción.'}
                                                </p>
                                                <Button asChild className="w-full">
                                                    <Link href={`/${tenant.slug}`}>Visitar perfil</Link>
                                                </Button>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            ) : (
                                <Empty aria-live="polite">
                                    <EmptyHeader>
                                        <EmptyIcon icon={Store} />
                                        <EmptyTitle>No sigues a ningún negocio</EmptyTitle>
                                        <EmptyDescription>
                                            Sigue a tus negocios favoritos para recibir actualizaciones y ofertas exclusivas.
                                        </EmptyDescription>
                                    </EmptyHeader>
                                    <EmptyContent>
                                        <Button asChild>
                                            <Link href="/directorio">Explorar Directorio</Link>
                                        </Button>
                                    </EmptyContent>
                                </Empty>
                            )}
                        </TabsContent>
                    </Tabs>
                </div>
            </main>
        </div>
    );
}
