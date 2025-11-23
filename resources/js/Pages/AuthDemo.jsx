import { Head } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import AuthGate from '@/Components/Utils/AuthGate';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Heart, ShoppingCart, Bell, MessageCircle } from 'lucide-react';
import { useUIStore } from '@/Stores/useUIStore';

export default function AuthDemo() {
    const openLoginModal = useUIStore((state) => state.openLoginModal);

    const handleAction = (action) => {
        alert(`¡Acción ejecutada: ${action}!`);
    };

    return (
        <AppLayout>
            <Head title="Demo - Sistema de Autenticación" />

            <div className="space-y-8">
                <div className="text-center">
                    <h1 className="text-4xl font-bold mb-2">Sistema de Autenticación Dual</h1>
                    <p className="text-muted-foreground">
                        Demostración del Login Gate Pattern y Modal Global
                    </p>
                </div>

                {/* Manual Modal Trigger */}
                <Card>
                    <CardHeader>
                        <CardTitle>1. Abrir Modal Manualmente</CardTitle>
                        <CardDescription>
                            Haz clic para abrir el modal de login sin interceptar ninguna acción
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button
                            onClick={() => openLoginModal()}
                            size="lg"
                            className="w-full sm:w-auto"
                        >
                            Abrir Modal de Login
                        </Button>
                    </CardContent>
                </Card>

                {/* AuthGate Examples */}
                <Card>
                    <CardHeader>
                        <CardTitle>2. Acciones Protegidas con AuthGate</CardTitle>
                        <CardDescription>
                            Estas acciones requieren autenticación. Si no estás logueado, se abrirá el modal automáticamente.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                            <AuthGate onAuth={() => handleAction('Añadir a favoritos')}>
                                <Button variant="outline" className="w-full gap-2">
                                    <Heart className="h-4 w-4" />
                                    Favoritos
                                </Button>
                            </AuthGate>

                            <AuthGate onAuth={() => handleAction('Agregar al carrito')}>
                                <Button variant="outline" className="w-full gap-2">
                                    <ShoppingCart className="h-4 w-4" />
                                    Carrito
                                </Button>
                            </AuthGate>

                            <AuthGate onAuth={() => handleAction('Activar notificaciones')}>
                                <Button variant="outline" className="w-full gap-2">
                                    <Bell className="h-4 w-4" />
                                    Notificaciones
                                </Button>
                            </AuthGate>

                            <AuthGate onAuth={() => handleAction('Contactar vendedor')}>
                                <Button variant="outline" className="w-full gap-2">
                                    <MessageCircle className="h-4 w-4" />
                                    Contactar
                                </Button>
                            </AuthGate>
                        </div>
                    </CardContent>
                </Card>

                {/* Flow Explanation */}
                <Card>
                    <CardHeader>
                        <CardTitle>Flujo de Autenticación</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4 text-sm">
                            <div className="flex gap-3">
                                <div className="bg-primary text-primary-foreground rounded-full w-6 h-6 flex items-center justify-center shrink-0">
                                    1
                                </div>
                                <div>
                                    <p className="font-medium">Usuario hace clic en acción protegida</p>
                                    <p className="text-muted-foreground">Ejemplo: "Añadir a favoritos"</p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="bg-primary text-primary-foreground rounded-full w-6 h-6 flex items-center justify-center shrink-0">
                                    2
                                </div>
                                <div>
                                    <p className="font-medium">AuthGate intercepta el clic</p>
                                    <p className="text-muted-foreground">Verifica si el usuario está autenticado</p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="bg-primary text-primary-foreground rounded-full w-6 h-6 flex items-center justify-center shrink-0">
                                    3
                                </div>
                                <div>
                                    <p className="font-medium">Modal se abre automáticamente</p>
                                    <p className="text-muted-foreground">Sin recargar la página, sin perder contexto</p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="bg-primary text-primary-foreground rounded-full w-6 h-6 flex items-center justify-center shrink-0">
                                    4
                                </div>
                                <div>
                                    <p className="font-medium">Usuario elige método de auth</p>
                                    <p className="text-muted-foreground">OTP (compradores) o Contraseña (vendedores)</p>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <div className="bg-primary text-primary-foreground rounded-full w-6 h-6 flex items-center justify-center shrink-0">
                                    5
                                </div>
                                <div>
                                    <p className="font-medium">Acción se ejecuta automáticamente</p>
                                    <p className="text-muted-foreground">Después de login exitoso, sin intervención manual</p>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Technical Details */}
                <Card>
                    <CardHeader>
                        <CardTitle>Detalles Técnicos</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                            <div className="space-y-2">
                                <h4 className="font-semibold">OTP (Compradores)</h4>
                                <ul className="space-y-1 text-muted-foreground">
                                    <li>• Código de 6 dígitos</li>
                                    <li>• Expira en 10 minutos</li>
                                    <li>• Max 3 intentos de generación/hora</li>
                                    <li>• Max 5 intentos de verificación</li>
                                    <li>• Hashing BCrypt</li>
                                </ul>
                            </div>

                            <div className="space-y-2">
                                <h4 className="font-semibold">Password (Vendedores)</h4>
                                <ul className="space-y-1 text-muted-foreground">
                                    <li>• Email + Contraseña</li>
                                    <li>• Opción "Recordarme"</li>
                                    <li>• Sesión segura</li>
                                    <li>• Redirección a /app</li>
                                </ul>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
