import { Head, useForm } from "@inertiajs/react"
import { Building2, ShieldCheck } from "lucide-react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { Alert, AlertDescription } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Field, FieldDescription, FieldGroup } from "@/Components/ui/field"
import { Spinner } from "@/Components/ui/spinner"

const ROLE_LABELS = {
    admin: "Administrador",
    manager: "Gerente",
    operator: "Operador",
    viewer: "Solo lectura",
}

export default function AcceptTenantInvitation({ invitation }) {
    const { post, processing, errors } = useForm({})

    const accept = (event) => {
        event.preventDefault()
        post(window.location.pathname, {
            preserveScroll: true,
        })
    }

    return (
        <>
            <Head title={`Invitación a ${invitation.tenantName}`}>
                <meta name="robots" content="noindex,nofollow,noarchive" />
                <meta name="referrer" content="no-referrer" />
            </Head>

            <AuthShadcnShell
                imageAlt="Acceso seguro al espacio de trabajo en Dovra AI"
                eyebrow="Invitación de equipo"
                title="Tu espacio de trabajo te espera"
                description="Revisa el negocio y el nivel de acceso antes de aceptar."
            >
                <Card className="overflow-hidden shadow-sm">
                    <CardHeader className="gap-4 border-b bg-muted/30">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex size-11 items-center justify-center rounded-lg border bg-background">
                                <Building2 aria-hidden="true" />
                            </div>
                            <Badge variant="secondary">{ROLE_LABELS[invitation.role] ?? invitation.role}</Badge>
                        </div>
                        <div className="space-y-2">
                            <CardTitle>{invitation.tenantName}</CardTitle>
                            <CardDescription>
                                {invitation.inviterName} te invitó como {ROLE_LABELS[invitation.role] ?? invitation.role}.
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-5 sm:p-6">
                        <form onSubmit={accept}>
                            <FieldGroup>
                                <Alert>
                                    <ShieldCheck aria-hidden="true" />
                                    <AlertDescription>
                                        Esta invitación está vinculada a {invitation.email} y vence el{" "}
                                        {new Intl.DateTimeFormat(undefined, {
                                            dateStyle: "medium",
                                            timeStyle: "short",
                                        }).format(new Date(invitation.expiresAt))}
                                        .
                                    </AlertDescription>
                                </Alert>

                                {errors.invitation ? (
                                    <Alert variant="destructive" role="alert">
                                        <AlertDescription>{errors.invitation}</AlertDescription>
                                    </Alert>
                                ) : null}

                                <Field>
                                    <Button type="submit" className="w-full" disabled={processing}>
                                        {processing ? <Spinner data-icon="inline-start" /> : null}
                                        {processing ? "Aceptando…" : "Aceptar invitación"}
                                    </Button>
                                    <FieldDescription className="text-center">
                                        Al aceptar, este negocio aparecerá entre tus espacios autorizados.
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </form>
                    </CardContent>
                </Card>
            </AuthShadcnShell>
        </>
    )
}
