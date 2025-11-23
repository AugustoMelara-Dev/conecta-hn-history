import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { User, Heart, Store, Shield, LogOut, MapPin } from 'lucide-react';

export default function UserDashboard({ auth }) {
    const user = auth.user;

    return (
        <>
            <Head title="Mi Cuenta" />

            <div className="min-h-screen bg-slate-50 flex">
                {/* Sidebar */}
                <aside className="w-64 bg-white border-r border-slate-200 hidden md:flex flex-col fixed h-full">
                    <div className="p-6 border-b border-slate-100">
                        <Link href="/" className="flex items-center gap-2">
                            <div className="w-8 h-8 bg-slate-900 rounded-lg flex items-center justify-center text-white font-bold font-serif">C</div>
                            <span className="font-serif font-bold text-lg tracking-tight text-slate-900">Conecta HN</span>
                        </Link>
                    </div>

                    <nav className="flex-1 p-4 space-y-1">
                        <Button variant="ghost" className="w-full justify-start text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                            <User className="mr-2 h-4 w-4" />
                            Mi Perfil
                        </Button>
                        <Button variant="ghost" className="w-full justify-start text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                            <Heart className="mr-2 h-4 w-4" />
                            Mis Favoritos
                        </Button>
                        <Button variant="ghost" className="w-full justify-start text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                            <Store className="mr-2 h-4 w-4" />
                            Negocios que Sigo
                        </Button>
                        <Button variant="ghost" className="w-full justify-start text-slate-600 hover:text-slate-900 hover:bg-slate-50">
                            <Shield className="mr-2 h-4 w-4" />
                            Seguridad
                        </Button>
                    </nav>

                    <div className="p-4 border-t border-slate-100">
                        <Link href={route('logout')} method="post" as="button" className="w-full">
                            <Button variant="outline" className="w-full justify-start text-red-600 hover:text-red-700 hover:bg-red-50 border-red-100">
                                <LogOut className="mr-2 h-4 w-4" />
                                Cerrar Sesión
                            </Button>
                        </Link>
                    </div>
                </aside>

                {/* Main Content */}
                <main className="flex-1 md:ml-64 p-8">
                    <div className="max-w-5xl mx-auto space-y-8">
                        {/* Header */}
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="font-serif text-3xl font-bold text-slate-900">Hola, {user.name}</h1>
                                <p className="text-slate-500">Bienvenido a tu panel personal.</p>
                            </div>
                            <Link href="/directorio">
                                <Button className="bg-slate-900 text-white hover:bg-slate-800">
                                    Explorar Directorio
                                </Button>
                            </Link>
                        </div>

                        {/* Recent Activity / Following Grid Placeholder */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                            {/* Example Card 1 */}
                            <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center font-bold text-slate-900">
                                        CE
                                    </div>
                                    <span className="px-2 py-1 bg-green-100 text-green-700 text-xs font-medium rounded-full">Abierto</span>
                                </div>
                                <div>
                                    <h3 className="font-semibold text-slate-900">Café El Indio</h3>
                                    <p className="text-sm text-slate-500 flex items-center gap-1">
                                        <MapPin className="w-3 h-3" /> Tegucigalpa
                                    </p>
                                </div>
                                <Button variant="outline" className="w-full text-slate-900 border-slate-200">
                                    Ver Perfil
                                </Button>
                            </div>

                            {/* Example Card 2 */}
                            <div className="bg-white p-6 rounded-xl border border-slate-200 shadow-sm space-y-4">
                                <div className="flex items-center justify-between">
                                    <div className="w-12 h-12 bg-slate-100 rounded-full flex items-center justify-center font-bold text-slate-900">
                                        TC
                                    </div>
                                    <span className="px-2 py-1 bg-red-100 text-red-700 text-xs font-medium rounded-full">Cerrado</span>
                                </div>
                                <div>
                                    <h3 className="font-semibold text-slate-900">Tech Center</h3>
                                    <p className="text-sm text-slate-500 flex items-center gap-1">
                                        <MapPin className="w-3 h-3" /> San Pedro Sula
                                    </p>
                                </div>
                                <Button variant="outline" className="w-full text-slate-900 border-slate-200">
                                    Ver Perfil
                                </Button>
                            </div>

                            {/* Empty State */}
                            <div className="bg-slate-50 p-6 rounded-xl border border-dashed border-slate-300 flex flex-col items-center justify-center text-center space-y-2">
                                <Store className="w-8 h-8 text-slate-400" />
                                <p className="text-sm text-slate-500 font-medium">Sigue más negocios</p>
                                <p className="text-xs text-slate-400">Aparecerán aquí para acceso rápido.</p>
                            </div>
                        </div>
                    </div>
                </main>
            </div>
        </>
    );
}
