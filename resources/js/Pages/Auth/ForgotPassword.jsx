import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ForgotPasswordForm } from "@/Components/Auth/ForgotPasswordForm"
import { t } from "@/lib/i18n"

export default function ForgotPassword({ status = null }) {
    return (
        <>
            <Head>
                <title>{t("auth.forgotPassword.headTitle")}</title>
                <meta
                    name="description"
                    content="Recupera el acceso a tu cuenta de negocio en Vito Business OS desde un flujo seguro y claro."
                />
                <link rel="canonical" href={route("password.request")} />
            </Head>

            <AuthShadcnShell
                imageAlt={t("auth.forgotPassword.imageAlt")}
                eyebrow="Recuperacion segura"
                title="Recupera el acceso con calma"
                description="Te enviaremos un enlace al correo de tu cuenta para que puedas volver a entrar de forma segura."
            >
                <ForgotPasswordForm status={status} />
            </AuthShadcnShell>
        </>
    )
}
