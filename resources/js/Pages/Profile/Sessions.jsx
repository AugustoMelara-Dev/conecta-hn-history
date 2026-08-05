import { Head, router } from "@inertiajs/react"
import { Laptop, ShieldCheck, Smartphone } from "lucide-react"

import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout"
import { Alert, AlertDescription } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"

function formatDate(value) {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: "medium",
        timeStyle: "short",
    }).format(new Date(value))
}

export default function Sessions({ auth, sessions = [] }) {
    const revoke = (id) => {
        router.delete(route("profile.sessions.destroy", { session: id }, false), {
            preserveScroll: true,
        })
    }

    const revokeOthers = () => {
        router.delete(route("profile.sessions.destroy-others", undefined, false), {
            preserveScroll: true,
        })
    }

    return (
        <AuthenticatedLayout user={auth?.user}>
            <Head title="Sesiones y dispositivos" />

            <div className="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6">
                <div className="space-y-2">
                    <p className="text-sm font-semibold uppercase tracking-wide text-muted-foreground">Seguridad</p>
                    <h1 className="text-3xl font-bold tracking-tight">Sesiones y dispositivos</h1>
                    <p className="text-muted-foreground">
                        Revisa dónde está abierta tu cuenta y revoca cualquier acceso que no reconozcas.
                    </p>
                </div>

                <Alert>
                    <ShieldCheck aria-hidden="true" />
                    <AlertDescription>
                        Las direcciones de red se guardan como hashes; Dovra no muestra ni persiste la IP en claro en este inventario.
                    </AlertDescription>
                </Alert>

                <div className="grid gap-4">
                    {sessions.map((session) => (
                        <Card key={session.id}>
                            <CardHeader className="flex-row items-start justify-between gap-4">
                                <div className="flex gap-3">
                                    <div className="mt-0.5 flex size-10 items-center justify-center rounded-lg border bg-muted">
                                        {session.deviceName.toLowerCase().includes("mobile") ? (
                                            <Smartphone aria-hidden="true" className="size-5" />
                                        ) : (
                                            <Laptop aria-hidden="true" className="size-5" />
                                        )}
                                    </div>
                                    <div>
                                        <CardTitle className="text-base">{session.deviceName}</CardTitle>
                                        <CardDescription>
                                            Última actividad: {formatDate(session.lastSeenAt)}
                                        </CardDescription>
                                    </div>
                                </div>
                                {session.isCurrent ? <Badge>Sesión actual</Badge> : null}
                            </CardHeader>
                            <CardContent className="flex flex-wrap items-center justify-between gap-3">
                                <p className="text-sm text-muted-foreground">
                                    Autenticada: {formatDate(session.lastAuthenticatedAt)}
                                    {session.mfaVerifiedAt ? " · Segundo factor verificado" : ""}
                                </p>
                                {!session.isCurrent ? (
                                    <Button variant="destructive" size="sm" onClick={() => revoke(session.id)}>
                                        Revocar
                                    </Button>
                                ) : null}
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {sessions.some((session) => !session.isCurrent) ? (
                    <Button variant="outline" onClick={revokeOthers}>
                        Cerrar todas las demás sesiones
                    </Button>
                ) : null}
            </div>
        </AuthenticatedLayout>
    )
}
