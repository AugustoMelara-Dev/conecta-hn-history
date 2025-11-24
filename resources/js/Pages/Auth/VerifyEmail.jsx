import PrimaryButton from '@/Components/PrimaryButton';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Mail } from 'lucide-react';

export default function VerifyEmail({ status }) {
    const { post, processing } = useForm({});

    const submit = (e) => {
        e.preventDefault();
        post(route('verification.send'));
    };

    return (
        <GuestLayout>
            <Head title="Verificar Correo" />

            <div className="flex items-center justify-center min-h-[80vh]">
                <Card className="w-full max-w-md shadow-lg border-border/50">
                    <CardHeader className="text-center space-y-2">
                        <div className="mx-auto w-12 h-12 bg-primary/10 rounded-full flex items-center justify-center mb-2">
                            <Mail className="w-6 h-6 text-primary" />
                        </div>
                        <CardTitle className="text-2xl font-bold">Verifica tu correo</CardTitle>
                        <CardDescription className="text-base">
                            ¡Gracias por registrarte! Antes de comenzar, por favor verifica tu dirección de correo electrónico haciendo clic en el enlace que te acabamos de enviar.
                        </CardDescription>
                    </CardHeader>

                    <CardContent>
                        {status === 'verification-link-sent' && (
                            <div className="mb-6 p-4 bg-green-50 dark:bg-green-900/20 text-sm font-medium text-green-600 dark:text-green-400 rounded-md border border-green-200 dark:border-green-900/50 text-center">
                                Se ha enviado un nuevo enlace de verificación a la dirección de correo que proporcionaste durante el registro.
                            </div>
                        )}

                        <div className="text-sm text-muted-foreground text-center mb-6">
                            ¿No recibiste el correo? Con gusto te enviaremos otro.
                        </div>

                        <form onSubmit={submit} className="space-y-4">
                            <Button className="w-full" disabled={processing}>
                                {processing ? 'Enviando...' : 'Reenviar Correo de Verificación'}
                            </Button>
                        </form>
                    </CardContent>

                    <CardFooter className="flex justify-center border-t bg-muted/20 pt-4">
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            className="text-sm text-muted-foreground hover:text-foreground underline decoration-muted-foreground/50 underline-offset-4 transition-colors"
                        >
                            Cerrar Sesión
                        </Link>
                    </CardFooter>
                </Card>
            </div>
        </GuestLayout>
    );
}
