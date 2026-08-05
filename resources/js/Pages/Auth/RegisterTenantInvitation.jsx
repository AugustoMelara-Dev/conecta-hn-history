import { Head, useForm } from "@inertiajs/react"
import { useEffect, useRef } from "react"
import { ShieldCheck, UserPlus } from "lucide-react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { Alert, AlertDescription } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Field, FieldDescription, FieldGroup, FieldLabel } from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"

const ROLE_LABELS = {
    admin: "Administrador",
    manager: "Gerente",
    operator: "Operador",
    viewer: "Solo lectura",
}

export default function RegisterTenantInvitation({ invitation }) {
    const { data, setData, post, processing, errors } = useForm({
        name: "",
        password: "",
        password_confirmation: "",
    })

    const nameRef = useRef(null)
    useEffect(() => {
        if (nameRef.current) nameRef.current.focus()
    }, [])

    const submit = (event) => {
        event.preventDefault()
        post(window.location.pathname, { preserveScroll: true })
    }

    return (
        <>
            <Head title={`Únete a ${invitation.tenantName}`}>
                <meta name="robots" content="noindex,nofollow,noarchive" />
                <meta name="referrer" content="no-referrer" />
            </Head>

            <AuthShadcnShell
                imageAlt="Alta segura de miembro de equipo en Dovra AI"
                eyebrow="Invitación de equipo"
                title="Crea tu acceso a Dovra AI"
                description="El correo de tu cuenta queda vinculado a la invitación recibida."
            >
                <Card className="overflow-hidden shadow-sm">
                    <CardHeader className="gap-4 border-b bg-muted/30">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex size-11 items-center justify-center rounded-lg border bg-background">
                                <UserPlus aria-hidden="true" />
                            </div>
                            <Badge variant="secondary">{ROLE_LABELS[invitation.role] ?? invitation.role}</Badge>
                        </div>
                        <div className="space-y-2">
                            <CardTitle>{invitation.tenantName}</CardTitle>
                            <CardDescription>
                                {invitation.inviterName} te invitó usando {invitation.email}.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-5 sm:p-6">
                        <form onSubmit={submit}>
                            <FieldGroup>
                                <Alert>
                                    <ShieldCheck aria-hidden="true" />
                                    <AlertDescription>
                                        La cuenta se activará únicamente para esta identidad y esta invitación.
                                    </AlertDescription>
                                </Alert>

                                {errors.invitation ? (
                                    <Alert variant="destructive" role="alert">
                                        <AlertDescription>{errors.invitation}</AlertDescription>
                                    </Alert>
                                ) : null}

                                <Field>
                                    <FieldLabel htmlFor="name">Nombre</FieldLabel>
                                    <Input
                                        id="name"
                                        name="name"
                                        autoComplete="name"
                                        ref={nameRef}
                                        value={data.name}
                                        onChange={(event) => setData("name", event.target.value)}
                                        required
                                    />
                                    {errors.name ? (
                                        <p className="text-sm text-destructive" role="alert">
                                            {errors.name}
                                        </p>
                                    ) : null}
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="password">Contraseña</FieldLabel>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autoComplete="new-password"
                                        value={data.password}
                                        onChange={(event) => setData("password", event.target.value)}
                                        required
                                    />
                                    {errors.password ? (
                                        <p className="text-sm text-destructive" role="alert">
                                            {errors.password}
                                        </p>
                                    ) : null}
                                    <FieldDescription>Usa una frase larga y única.</FieldDescription>
                                </Field>

                                <Field>
                                    <FieldLabel htmlFor="password_confirmation">Confirmar contraseña</FieldLabel>
                                    <Input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        type="password"
                                        autoComplete="new-password"
                                        value={data.password_confirmation}
                                        onChange={(event) => setData("password_confirmation", event.target.value)}
                                        required
                                    />
                                </Field>

                                <Button type="submit" className="w-full" disabled={processing}>
                                    {processing ? <Spinner data-icon="inline-start" /> : null}
                                    {processing ? "Creando acceso…" : "Crear cuenta y aceptar"}
                                </Button>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </AuthShadcnShell>
        </>
    )
}
