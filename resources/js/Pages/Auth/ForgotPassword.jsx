import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { KeyRound, ArrowLeft } from 'lucide-react';

export default function ForgotPassword({ status }) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Recuperar Contraseña" />

            <div className="flex items-center justify-center min-h-[80vh]">
                <Card className="w-full max-w-md shadow-lg border-border/50">
                    <CardHeader className="text-center space-y-2">
                        <div className="mx-auto w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mb-2">
                            <KeyRound className="w-6 h-6 text-primary" />
                        </div>
                        <CardTitle className="text-2xl font-bold">¿Olvidaste tu contraseña?</CardTitle>
                        <CardDescription className="text-base">
                            No te preocupes. Ingresa tu correo electrónico y te enviaremos un enlace para restablecerla.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        {status && (
                            <div className="mb-6 p-4 bg-green-50 dark:bg-green-900/20 text-sm font-medium text-green-600 dark:text-green-400 rounded-md border border-green-200 dark:border-green-900/50 text-center">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="email">Correo Electrónico</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    value={data.email}
                                    className="block w-full"
                                    autoFocus
                                    onChange={(e) => setData('email', e.target.value)}
                                    placeholder="nombre@ejemplo.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <Button className="w-full" disabled={processing}>
                                {processing ? 'Enviando enlace...' : 'Enviar enlace de recuperación'}
                            </Button>
                        </form>
                    </CardContent>

                    <CardFooter className="flex justify-center border-t bg-muted/20 pt-4">
                        <Link
                            href={route('login')}
                            className="flex items-center text-sm text-muted-foreground hover:text-foreground transition-colors"
                        >
                            <ArrowLeft className="w-4 h-4 mr-2" />
                            Volver al inicio de sesión
                        </Link>
                    </CardFooter>
                </Card>
            </div>
        </GuestLayout>
    );
}
