import { Head, Link, useForm, usePage } from "@inertiajs/react"
import { ArrowLeft, KeyRound, RefreshCw, ShieldCheck, ShieldOff } from "lucide-react"

import InputError from "@/Components/InputError"
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Input } from "@/Components/ui/input"
import { Label } from "@/Components/ui/label"
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"

export default function TwoFactor({
    enabled = false,
    pendingConfirmation = false,
    requiredForPlatformAdmin = false,
    qrCodeDataUri = null,
    recoveryCodes = [],
}) {
    const flash = usePage().props.flash || {}
    const enableForm = useForm({})
    const confirmForm = useForm({ code: "" })
    const recoveryForm = useForm({})
    const disableForm = useForm({})

    const enable = () => enableForm.post(route("profile.two-factor.enable"), { preserveScroll: true })
    const confirm = (event) => {
        event.preventDefault()
        confirmForm.post(route("profile.two-factor.confirm"), {
            preserveScroll: true,
            onFinish: () => confirmForm.reset("code"),
        })
    }

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-2">
                    <Badge className="w-fit" variant="secondary">Seguridad de cuenta</Badge>
                    <h1 className="text-2xl font-semibold text-foreground">Verificacion en dos pasos</h1>
                    <p className="max-w-2xl text-sm leading-6 text-muted-foreground">
                        Protege el acceso con una aplicacion autenticadora compatible con TOTP y codigos de recuperacion de un solo uso.
                    </p>
                </div>
            }
        >
            <Head title="Verificacion en dos pasos" />
            <main className="bg-background py-10">
                <div className="mx-auto flex max-w-3xl flex-col gap-6 px-4 sm:px-6">
                    <Button asChild variant="ghost" className="w-fit">
                        <Link href={route("profile.edit")}><ArrowLeft className="mr-2 size-4" aria-hidden="true" />Volver al perfil</Link>
                    </Button>

                    {flash.success ? (
                        <Alert aria-live="polite"><ShieldCheck aria-hidden="true" /><AlertTitle>Listo</AlertTitle><AlertDescription>{flash.success}</AlertDescription></Alert>
                    ) : null}

                    {requiredForPlatformAdmin ? (
                        <Alert variant="warning"><ShieldCheck aria-hidden="true" /><AlertTitle>Obligatorio para plataforma</AlertTitle><AlertDescription>El panel superadmin permanece bloqueado hasta que confirmes el segundo factor y no permite desactivarlo.</AlertDescription></Alert>
                    ) : null}

                    {!enabled && !pendingConfirmation ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Activa la proteccion</CardTitle>
                                <CardDescription>Necesitas una aplicacion como Google Authenticator, Microsoft Authenticator, 1Password o Authy.</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <Button onClick={enable} disabled={enableForm.processing}>
                                    <KeyRound className="mr-2 size-4" aria-hidden="true" />
                                    {enableForm.processing ? "Preparando..." : "Configurar segundo factor"}
                                </Button>
                            </CardContent>
                        </Card>
                    ) : null}

                    {pendingConfirmation ? (
                        <Card>
                            <CardHeader>
                                <CardTitle>Escanea y confirma</CardTitle>
                                <CardDescription>Escanea el QR, escribe el codigo de seis digitos y confirma antes de salir.</CardDescription>
                            </CardHeader>
                            <CardContent className="grid gap-6 sm:grid-cols-[220px_1fr] sm:items-center">
                                {qrCodeDataUri ? <img src={qrCodeDataUri} alt="Codigo QR para configurar la aplicacion autenticadora" className="size-[220px] rounded-lg border bg-white p-3" /> : null}
                                <form className="grid gap-4" onSubmit={confirm}>
                                    <div className="grid gap-2">
                                        <Label htmlFor="code">Codigo temporal</Label>
                                        <Input id="code" inputMode="numeric" autoComplete="one-time-code" maxLength={6} pattern="[0-9]*" value={confirmForm.data.code} onChange={(event) => confirmForm.setData("code", event.target.value.replace(/\D/g, "").slice(0, 6))} required />
                                        <InputError message={confirmForm.errors.code} />
                                    </div>
                                    <Button type="submit" disabled={confirmForm.processing || confirmForm.data.code.length !== 6}>Confirmar activacion</Button>
                                </form>
                            </CardContent>
                        </Card>
                    ) : null}

                    {enabled ? (
                        <>
                            <Card>
                                <CardHeader>
                                    <div className="flex items-start justify-between gap-4">
                                        <div><CardTitle>Segundo factor activo</CardTitle><CardDescription>Se pedira un codigo adicional en cada nuevo inicio de sesion.</CardDescription></div>
                                        <Badge><ShieldCheck className="mr-1 size-3" aria-hidden="true" />Activo</Badge>
                                    </div>
                                </CardHeader>
                            </Card>
                            <Card>
                                <CardHeader><CardTitle>Codigos de recuperacion</CardTitle><CardDescription>Guarda estos codigos fuera del dispositivo. Cada codigo funciona una sola vez.</CardDescription></CardHeader>
                                <CardContent className="grid gap-4">
                                    <div className="grid gap-2 rounded-lg border bg-muted/30 p-4 sm:grid-cols-2" aria-label="Codigos de recuperacion">
                                        {recoveryCodes.map((code) => <code key={code} className="font-mono text-sm">{code}</code>)}
                                    </div>
                                    <Button variant="outline" className="w-fit" onClick={() => recoveryForm.post(route("profile.two-factor.recovery-codes"), { preserveScroll: true })} disabled={recoveryForm.processing}>
                                        <RefreshCw className="mr-2 size-4" aria-hidden="true" />Regenerar codigos
                                    </Button>
                                </CardContent>
                            </Card>
                            {!requiredForPlatformAdmin ? (
                                <Card>
                                    <CardHeader><CardTitle>Desactivar</CardTitle><CardDescription>Tu cuenta volvera a depender solo de la contrasena. No se recomienda.</CardDescription></CardHeader>
                                    <CardContent>
                                        <Button variant="destructive" onClick={() => disableForm.delete(route("profile.two-factor.destroy"), { preserveScroll: true })} disabled={disableForm.processing}>
                                            <ShieldOff className="mr-2 size-4" aria-hidden="true" />Desactivar segundo factor
                                        </Button>
                                        <InputError message={disableForm.errors.two_factor} className="mt-2" />
                                    </CardContent>
                                </Card>
                            ) : null}
                        </>
                    ) : null}
                </div>
            </main>
        </AuthenticatedLayout>
    )
}