import { Head, Link, useForm, usePage } from "@inertiajs/react"
import { ArrowRight, MailCheck, RefreshCcw } from "lucide-react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/Components/ui/card"
import { Field, FieldDescription, FieldGroup, FieldSeparator } from "@/Components/ui/field"

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
            <Head title={t("headTitle")} />

            <div className="flex min-h-svh w-full items-center justify-center bg-[#f7f8fb] p-6 md:p-10">
                <div className="w-full max-w-md">
                    <Card className="border-slate-200 bg-white text-slate-950 shadow-xl shadow-slate-200/60">
                        <CardHeader>
                            <div className="mb-2 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-700">
                                <MailCheck className="h-6 w-6" aria-hidden="true" />
                            </div>
                            <CardTitle className="text-2xl font-semibold text-slate-950">{t("title")}</CardTitle>
                            <CardDescription className="text-slate-500">
                                {t("descriptionPrefix")} <span className="font-medium text-slate-950">{auth?.user?.email}</span>.{" "}
                                {t("descriptionSuffix")}
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {status === "verification-link-sent" ? (
                                    <Alert variant="success" aria-live="polite" data-testid="verify-email-feedback">
                                        <AlertTitle>{t("resentTitle")}</AlertTitle>
                                        <AlertDescription>{t("resentDescription")}</AlertDescription>
                                    </Alert>
                                ) : null}

                                {onboardingNextAction ? (
                                    <Alert data-testid="verify-email-onboarding-next">
                                        <ArrowRight className="h-4 w-4" aria-hidden="true" />
                                        <AlertTitle>{t("nextActionTitle", { title: onboardingNextAction.title })}</AlertTitle>
                                        <AlertDescription>
                                            {onboardingNextAction.copy}
                                            {typeof onboardingNextAction.progress === "number" ? (
                                                <span className="mt-2 block font-medium text-slate-700">
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
                                            className="w-full bg-slate-950 text-white hover:bg-slate-800"
                                        >
                                            <RefreshCcw
                                                className={`mr-2 h-4 w-4 ${processing ? "animate-spin" : ""}`}
                                                aria-hidden="true"
                                            />
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
                                        className="w-full border-slate-200 bg-white text-slate-950 hover:bg-slate-50"
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
                </div>
            </div>
        </>
    )
}
