import { Head, Link, useForm, usePage } from "@inertiajs/react"
import { ArrowRight, MailCheck, RefreshCcw } from "lucide-react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Field, FieldDescription, FieldGroup, FieldSeparator } from "@/Components/ui/field"
import { Spinner } from "@/Components/ui/spinner"

const COPY = {
    headTitle: "Verificar correo",
    title: "Confirma tu correo",
    descriptionPrefix: "Te enviamos un enlace de verificacion a",
    descriptionSuffix: "Abre ese correo y vuelve aqui para continuar.",
    resentTitle: "Enlace reenviado",
    resentDescription: "Te enviamos un nuevo enlace de verificacion.",
    nextActionTitle: "Siguiente paso: {title}",
    progressLabel: "Progreso de onboarding: {progress}%",
    resendIdle: "Reenviar correo de verificacion",
    resendProcessing: "Reenviando...",
    alternateAccount: "o usa otra cuenta",
    logout: "Cerrar sesion",
    footerDescription: "Si cambiaste de correo, cierra sesion y vuelve a ingresar con la cuenta correcta.",
}

function t(key, params = {}) {
    const template = COPY[key] ?? key
    return Object.entries(params).reduce((text, [paramKey, value]) => text.replace(`{${paramKey}}`, String(value)), template)
}

export default function VerifyEmail({ status, onboardingNextAction = null }) {
    const { auth } = usePage().props
    const { post, processing } = useForm({})

    const submit = (event) => {
        event.preventDefault()
        post(route("verification.send"))
    }

    return (
        <>
            <Head>
                <title>{t("headTitle")}</title>
                <meta
                    name="description"
                    content="Verifica tu correo para completar el acceso seguro al panel de negocio en Vito Business OS."
                />
                <link rel="canonical" href={route("verification.notice")} />
            </Head>

            <AuthShadcnShell
                imageAlt="Verificacion de correo para Vito Business OS"
                eyebrow="Correo pendiente"
                title="Un paso mas para proteger tu cuenta"
                description="La verificacion confirma que los avisos de acceso y seguridad lleguen al correo correcto."
            >
                <Card className="overflow-hidden shadow-sm">
                    <CardHeader className="gap-3 border-b bg-muted/30">
                        <div className="flex items-center justify-between gap-3">
                            <div className="flex size-11 items-center justify-center rounded-lg border bg-background text-muted-foreground">
                                <MailCheck aria-hidden="true" />
                            </div>
                            <Badge variant="secondary">Accion requerida</Badge>
                        </div>
                        <div className="flex flex-col gap-2">
                            <CardTitle>{t("title")}</CardTitle>
                            <CardDescription>
                                {t("descriptionPrefix")} <span className="font-medium text-foreground">{auth?.user?.email}</span>.{" "}
                                {t("descriptionSuffix")}
                            </CardDescription>
                        </div>
                    </CardHeader>
                    <CardContent className="p-5 sm:p-6">
                        <FieldGroup>
                            {status === "verification-link-sent" ? (
                                <Alert variant="success" aria-live="polite" data-testid="verify-email-feedback">
                                    <AlertTitle>{t("resentTitle")}</AlertTitle>
                                    <AlertDescription>{t("resentDescription")}</AlertDescription>
                                </Alert>
                            ) : null}

                            {onboardingNextAction ? (
                                <Alert data-testid="verify-email-onboarding-next">
                                    <ArrowRight aria-hidden="true" />
                                    <AlertTitle>{t("nextActionTitle", { title: onboardingNextAction.title })}</AlertTitle>
                                    <AlertDescription>
                                        {onboardingNextAction.copy}
                                        {typeof onboardingNextAction.progress === "number" ? (
                                            <span className="mt-2 block font-medium text-foreground">
                                                {t("progressLabel", { progress: onboardingNextAction.progress })}
                                            </span>
                                        ) : null}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <Field>
                                <form onSubmit={submit}>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        data-testid="verify-email-resend"
                                        className="w-full"
                                    >
                                        {processing ? (
                                            <Spinner data-icon="inline-start" />
                                        ) : (
                                            <RefreshCcw data-icon="inline-start" aria-hidden="true" />
                                        )}
                                        {processing ? t("resendProcessing") : t("resendIdle")}
                                    </Button>
                                </form>
                            </Field>

                            <FieldSeparator>{t("alternateAccount")}</FieldSeparator>

                            <Field>
                                <Button
                                    variant="outline"
                                    type="button"
                                    data-testid="verify-email-logout"
                                    className="w-full"
                                    asChild
                                >
                                    <Link href={route("logout")} method="post" as="button">
                                        {t("logout")}
                                    </Link>
                                </Button>
                                <FieldDescription className="text-center">{t("footerDescription")}</FieldDescription>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                </Card>
            </AuthShadcnShell>
        </>
    )
}
