import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { useUIStore } from '@/Stores/useUIStore';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/Components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/Components/ui/tabs';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Loader2, Smartphone, Lock, ArrowRight } from 'lucide-react';
import axios from 'axios';

export default function GlobalLoginModal() {
    const { isLoginModalOpen, closeLoginModal, loginView, setLoginView } = useUIStore();
    const [otpStep, setOtpStep] = useState('identifier'); // 'identifier' | 'verify'
    const [otpIdentifier, setOtpIdentifier] = useState('');
    const [isLoadingOtp, setIsLoadingOtp] = useState(false);
    const [otpError, setOtpError] = useState('');
    const [otpSuccess, setOtpSuccess] = useState('');

    // Password login form (Inertia)
    const passwordForm = useForm({
        email: '',
        password: '',
        remember: false,
    });

    // OTP identifier form
    const [identifier, setIdentifier] = useState('');

    // OTP verification form
    const [code, setCode] = useState('');

    // Handle password login
    const handlePasswordLogin = (e) => {
        e.preventDefault();
        passwordForm.post(route('login'), {
            onSuccess: () => {
                closeLoginModal();
                passwordForm.reset();
            },
        });
    };

    // Handle OTP generation
    const handleGenerateOtp = async (e) => {
        e.preventDefault();
        setIsLoadingOtp(true);
        setOtpError('');
        setOtpSuccess('');

        try {
            const response = await axios.post('/api/otp/generate', {
                identifier: identifier,
            });

            setOtpSuccess(response.data.message);
            setOtpIdentifier(identifier);
            setOtpStep('verify');
        } catch (error) {
            setOtpError(error.response?.data?.message || 'Error al enviar el código');
        } finally {
            setIsLoadingOtp(false);
        }
    };

    // Handle OTP verification
    const handleVerifyOtp = async (e) => {
        e.preventDefault();
        setIsLoadingOtp(true);
        setOtpError('');

        try {
            const response = await axios.post('/api/otp/verify', {
                identifier: otpIdentifier,
                code: code,
            });

            if (response.data.requires_registration) {
                // Redirect to registration
                window.location.href = '/register?identifier=' + encodeURIComponent(otpIdentifier);
            } else {
                // Success - reload to get authenticated state
                window.location.href = response.data.redirect;
            }
        } catch (error) {
            setOtpError(error.response?.data?.errors?.code?.[0] || 'Código inválido');
        } finally {
            setIsLoadingOtp(false);
        }
    };

    // Reset OTP flow when closing modal
    const handleClose = () => {
        closeLoginModal();
        setOtpStep('identifier');
        setIdentifier('');
        setCode('');
        setOtpError('');
        setOtpSuccess('');
        passwordForm.reset();
    };

    return (
        <Dialog open={isLoginModalOpen} onOpenChange={handleClose}>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle className="text-2xl font-bold">Iniciar Sesión</DialogTitle>
                    <DialogDescription>
                        Accede a tu cuenta para continuar
                    </DialogDescription>
                </DialogHeader>

                <Tabs value={loginView} onValueChange={setLoginView} className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="otp" className="gap-2">
                            <Smartphone className="h-4 w-4" />
                            Comprador
                        </TabsTrigger>
                        <TabsTrigger value="password" className="gap-2">
                            <Lock className="h-4 w-4" />
                            Vendedor
                        </TabsTrigger>
                    </TabsList>

                    {/* OTP Login (Buyers) */}
                    <TabsContent value="otp" className="space-y-4 mt-4">
                        {otpStep === 'identifier' ? (
                            <form onSubmit={handleGenerateOtp} className="space-y-4">
                                <div>
                                    <Label htmlFor="identifier">Email o Teléfono</Label>
                                    <Input
                                        id="identifier"
                                        type="text"
                                        placeholder="ejemplo@correo.com o +504..."
                                        value={identifier}
                                        onChange={(e) => setIdentifier(e.target.value)}
                                        required
                                        className="mt-1"
                                    />
                                </div>

                                {otpError && (
                                    <div className="bg-destructive/10 text-destructive text-sm p-3 rounded-lg">
                                        {otpError}
                                    </div>
                                )}

                                <Button type="submit" className="w-full" disabled={isLoadingOtp}>
                                    {isLoadingOtp ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            Enviando...
                                        </>
                                    ) : (
                                        <>
                                            Enviar Código
                                            <ArrowRight className="ml-2 h-4 w-4" />
                                        </>
                                    )}
                                </Button>
                            </form>
                        ) : (
                            <form onSubmit={handleVerifyOtp} className="space-y-4">
                                <div className="bg-primary/10 p-3 rounded-lg text-sm">
                                    <p className="text-primary font-medium">
                                        Código enviado a {otpIdentifier}
                                    </p>
                                </div>

                                <div>
                                    <Label htmlFor="code">Código de 6 dígitos</Label>
                                    <Input
                                        id="code"
                                        type="text"
                                        placeholder="000000"
                                        maxLength="6"
                                        value={code}
                                        onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                                        required
                                        className="mt-1 text-center text-2xl tracking-widest"
                                        autoFocus
                                    />
                                </div>

                                {otpError && (
                                    <div className="bg-destructive/10 text-destructive text-sm p-3 rounded-lg">
                                        {otpError}
                                    </div>
                                )}

                                <div className="flex gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setOtpStep('identifier')}
                                        className="flex-1"
                                    >
                                        Volver
                                    </Button>
                                    <Button type="submit" className="flex-1" disabled={isLoadingOtp || code.length !== 6}>
                                        {isLoadingOtp ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                Verificando...
                                            </>
                                        ) : (
                                            'Verificar'
                                        )}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </TabsContent>

                    {/* Password Login (Sellers) */}
                    <TabsContent value="password" className="space-y-4 mt-4">
                        <form onSubmit={handlePasswordLogin} className="space-y-4">
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={passwordForm.data.email}
                                    onChange={(e) => passwordForm.setData('email', e.target.value)}
                                    required
                                    className="mt-1"
                                    autoComplete="email"
                                />
                                {passwordForm.errors.email && (
                                    <p className="text-sm text-destructive mt-1">{passwordForm.errors.email}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="password">Contraseña</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={passwordForm.data.password}
                                    onChange={(e) => passwordForm.setData('password', e.target.value)}
                                    required
                                    className="mt-1"
                                    autoComplete="current-password"
                                />
                                {passwordForm.errors.password && (
                                    <p className="text-sm text-destructive mt-1">{passwordForm.errors.password}</p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <input
                                    id="remember"
                                    type="checkbox"
                                    checked={passwordForm.data.remember}
                                    onChange={(e) => passwordForm.setData('remember', e.target.checked)}
                                    className="rounded"
                                />
                                <Label htmlFor="remember" className="text-sm font-normal cursor-pointer">
                                    Recordarme
                                </Label>
                            </div>

                            <Button type="submit" className="w-full" disabled={passwordForm.processing}>
                                {passwordForm.processing ? (
                                    <>
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        Iniciando...
                                    </>
                                ) : (
                                    'Iniciar Sesión'
                                )}
                            </Button>
                        </form>
                    </TabsContent>
                </Tabs>
            </DialogContent>
        </Dialog>
    );
}
