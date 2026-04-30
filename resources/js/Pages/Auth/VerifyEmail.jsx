import { Head, Link, useForm, usePage } from "@inertiajs/react"
import { MailCheck, RefreshCcw } from "lucide-react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldSeparator,
} from "@/Components/ui/field"

export default function VerifyEmail({ status }) {
    const { auth } = usePage().props
    const { post, processing } = useForm({})

    const submit = (event) => {
        event.preventDefault()
        post(route("verification.send"))
    }

    return (
        <>
            <Head title="Verifica tu correo | Vito Business OS" />

            <div className="flex min-h-svh w-full items-center justify-center bg-[#f7f8fb] p-6 md:p-10">
                <div className="w-full max-w-md">
                    <Card className="border-slate-200 bg-white text-slate-950 shadow-xl shadow-slate-200/60">
                        <CardHeader>
                            <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                <MailCheck className="h-6 w-6" aria-hidden="true" />
                            </div>
                            <CardTitle className="text-2xl font-semibold text-slate-950">
                                Verifica tu correo
                            </CardTitle>
                            <CardDescription className="text-slate-500">
                                Enviamos un enlace de verificación a{" "}
                                <span className="font-medium text-slate-950">
                                    {auth?.user?.email}
                                </span>
                                . Ábrelo para desbloquear el panel correcto de Vito Business OS.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {status === "verification-link-sent" ? (
                                    <Alert variant="success" aria-live="polite" data-testid="verify-email-feedback">
                                        <AlertTitle>Correo reenviado</AlertTitle>
                                        <AlertDescription>
                                            Te enviamos un nuevo enlace de verificación. Revisa también spam o promociones.
                                        </AlertDescription>
                                    </Alert>
                                ) : null}

                                <Field>
                                    <form onSubmit={submit}>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            data-testid="verify-email-resend"
                                            className="w-full bg-slate-950 text-white hover:bg-slate-800"
                                        >
                                            <RefreshCcw
                                                className={`mr-2 h-4 w-4 ${processing ? "animate-spin" : ""}`}
                                                aria-hidden="true"
                                            />
                                            {processing ? "Enviando..." : "Reenviar correo de verificación"}
                                        </Button>
                                    </form>
                                </Field>

                                <FieldSeparator>¿Necesitas otra cuenta?</FieldSeparator>

                                <Field>
                                    <Button
                                        variant="outline"
                                        type="button"
                                        data-testid="verify-email-logout"
                                        className="w-full border-slate-200 bg-white text-slate-950 hover:bg-slate-50"
                                        asChild
                                    >
                                        <Link href={route("logout")} method="post" as="button">
                                            Cerrar sesión
                                        </Link>
                                    </Button>
                                    <FieldDescription className="text-center">
                                        Verifica tu correo para continuar al panel que corresponde a tu cuenta.
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    )
}
