import { useState, useEffect } from 'react';
import { Head, Link, usePage, router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar';
import { User, Heart, Store, Shield, LogOut, MapPin, TrendingUp } from 'lucide-react';
import AnalyticsChart from '@/Components/Organisms/AnalyticsChart';

export default function UserDashboard({ auth }) {
    const [activeTab, setActiveTab] = useState('profile');
    const [analyticsData, setAnalyticsData] = useState(null);
    const [loadingAnalytics, setLoadingAnalytics] = useState(false);
    const user = auth.user;
    const isSeller = user.role === 'seller';

    // Mock Data for UI Development
    const following = [
        { id: 1, name: "Café El Indio", category: "Cafetería", status: "open", image: null },
        { id: 2, name: "Ferretería Central", category: "Construcción", status: "closed", image: null },
    ];

    const favorites = [
        { id: 1, name: "Café Molido 1lb", price: "L. 120.00", business: "Café El Indio" },
    ];

    // Pilar 8: Fetch analytics data for sellers
    useEffect(() => {
        if (isSeller && activeTab === 'analytics') {
            setLoadingAnalytics(true);
            fetch(route('analytics.data'))
                .then(res => res.json())
                .then(data => {
                    setAnalyticsData(data);
                    setLoadingAnalytics(false);
                })
                .catch(err => {
                    console.error('Failed to load analytics:', err);
                    setLoadingAnalytics(false);
                });
        }
    }, [activeTab, isSeller]);

    return (
        <>
            <Head title="Mi Cuenta - Conecta HN" />

            <div className="min-h-screen bg-slate-50 font-sans text-slate-900">
                <div className="flex h-screen overflow-hidden">
                    {/* Sidebar */}
                    <aside className="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col">
                        <div className="p-6 border-b border-slate-100">
                            <Link href="/" className="flex items-center gap-2">
                                <div className="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center">
                                    <span className="text-white font-bold text-lg">C</span>
                                </div>
                                <span className="font-bold text-xl tracking-tight">Conecta HN</span>
                            </Link>
                        </div>

                        <nav className="flex-1 p-4 space-y-1">
                            <Button
                                variant={activeTab === 'profile' ? 'secondary' : 'ghost'}
                                className="w-full justify-start"
                                onClick={() => setActiveTab('profile')}
                            >
                                <User className="mr-2 h-4 w-4" /> Perfil
                            </Button>
                            <Button
                                variant={activeTab === 'following' ? 'secondary' : 'ghost'}
                                className="w-full justify-start"
                                onClick={() => setActiveTab('following')}
                            >
                                <Store className="mr-2 h-4 w-4" /> Negocios Seguidos
                            </Button>
                            <Button
                                variant={activeTab === 'favorites' ? 'secondary' : 'ghost'}
                                className="w-full justify-start"
                                onClick={() => setActiveTab('favorites')}
                            >
                                <Heart className="mr-2 h-4 w-4" /> Favoritos
                            </Button>
                            <Button
                                variant={activeTab === 'security' ? 'secondary' : 'ghost'}
                                className="w-full justify-start"
                                onClick={() => setActiveTab('security')}
                            >
                                <Shield className="mr-2 h-4 w-4" /> Seguridad
                            </Button>

                            {/* Pilar 8: Analytics tab for sellers */}
                            {isSeller && (
                                <Button
                                    variant={activeTab === 'analytics' ? 'secondary' : 'ghost'}
                                    className="w-full justify-start"
                                    onClick={() => setActiveTab('analytics')}
                                >
                                    <TrendingUp className="mr-2 h-4 w-4" /> Analíticas
                                </Button>
                            )}
                        </nav>

                        <div className="p-4 border-t border-slate-100">
                            <Link href={route('logout')} method="post" as="button" className="w-full">
                                <Button variant="outline" className="w-full border-red-200 text-red-600 hover:bg-red-50 hover:text-red-700">
                                    <LogOut className="mr-2 h-4 w-4" /> Cerrar Sesión
                                </Button>
                            </Link>
                        </div>
                    </aside>

                    {/* Main Content */}
                    <main className="flex-1 overflow-y-auto p-8">
                        <div className="max-w-4xl mx-auto">
                            <header className="mb-8 flex items-center justify-between">
                                <div>
                                    <h1 className="text-2xl font-bold text-slate-900">Hola, {user.name}</h1>
                                    <p className="text-slate-500">Bienvenido a tu panel personal.</p>
                                </div>
                                <Avatar className="h-12 w-12">
                                    <AvatarImage src={`https://ui-avatars.com/api/?name=${user.name}&background=0f172a&color=fff`} />
                                    <AvatarFallback>CN</AvatarFallback>
                                </Avatar>
                            </header>

                            {activeTab === 'profile' && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    <Card>
                                        <CardHeader>
                                            <CardTitle>Información Personal</CardTitle>
                                        </CardHeader>
                                        <CardContent className="space-y-4">
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div className="space-y-2">
                                                    <label className="text-sm font-medium text-slate-700">Nombre Completo</label>
                                                    <input type="text" value={user.name} disabled className="w-full p-2 rounded-md border border-slate-200 bg-slate-50 text-slate-500" />
                                                </div>
                                                <div className="space-y-2">
                                                    <label className="text-sm font-medium text-slate-700">Correo Electrónico</label>
                                                    <input type="email" value={user.email} disabled className="w-full p-2 rounded-md border border-slate-200 bg-slate-50 text-slate-500" />
                                                </div>
                                            </div>
                                            <Button className="mt-4 bg-slate-900 text-white">Guardar Cambios</Button>
                                        </CardContent>
                                    </Card>
                                </div>
                            )}

                            {activeTab === 'following' && (
                                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    {following.map((business) => (
                                        <Card key={business.id} className="overflow-hidden hover:shadow-md transition-shadow">
                                            <div className="h-24 bg-slate-100 relative">
                                                <div className="absolute -bottom-6 left-4 w-12 h-12 bg-white rounded-full border-2 border-white shadow-sm flex items-center justify-center">
                                                    <Store className="w-6 h-6 text-slate-400" />
                                                </div>
                                            </div>
                                            <CardContent className="pt-8 pb-4 px-4">
                                                <h3 className="font-bold text-slate-900">{business.name}</h3>
                                                <p className="text-sm text-slate-500 mb-4">{business.category}</p>
                                                <div className="flex items-center justify-between">
                                                    <span className={`text-xs font-medium px-2 py-1 rounded-full ${business.status === 'open' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                                                        {business.status === 'open' ? 'Abierto' : 'Cerrado'}
                                                    </span>
                                                    <Button variant="outline" size="sm" className="text-xs">Dejar de Seguir</Button>
                                                </div>
                                            </CardContent>
                                        </Card>
                                    ))}
                                </div>
                            )}

                            {activeTab === 'favorites' && (
                                <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    {favorites.map((item) => (
                                        <div key={item.id} className="flex items-center gap-4 p-4 bg-white rounded-xl border border-slate-100 hover:shadow-sm transition-shadow">
                                            <div className="w-16 h-16 bg-slate-100 rounded-lg flex items-center justify-center">
                                                <Heart className="w-6 h-6 text-slate-300" />
                                            </div>
                                            <div className="flex-1">
                                                <h3 className="font-bold text-slate-900">{item.name}</h3>
                                                <p className="text-sm text-slate-500">{item.business}</p>
                                            </div>
                                            <div className="text-right">
                                                <p className="font-bold text-slate-900">{item.price}</p>
                                                <Button variant="ghost" size="sm" className="text-red-500 hover:text-red-600 hover:bg-red-50">
                                                    Eliminar
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Pilar 8: Analytics Tab for Sellers */}
                            {activeTab === 'analytics' && isSeller && (
                                <div className="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-500">
                                    <div className="mb-6">
                                        <h2 className="text-2xl font-bold text-slate-900 mb-2">Rendimiento de tu Negocio</h2>
                                        <p className="text-slate-500">Visitas únicas a tu micrositio en los últimos 30 días</p>
                                    </div>

                                    {loadingAnalytics ? (
                                        <Card className="p-12">
                                            <div className="text-center">
                                                <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-slate-900 mx-auto mb-4"></div>
                                                <p className="text-slate-500">Cargando estadísticas...</p>
                                            </div>
                                        </Card>
                                    ) : analyticsData ? (
                                        <AnalyticsChart data={analyticsData.data} summary={analyticsData.summary} />
                                    ) : (
                                        <Card className="p-12">
                                            <div className="text-center text-slate-500">
                                                <TrendingUp className="w-12 h-12 mx-auto mb-4 opacity-30" />
                                                <p>No se pudieron cargar las estadísticas</p>
                                            </div>
                                        </Card>
                                    )}
                                </div>
                            )}
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
