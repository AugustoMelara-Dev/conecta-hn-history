import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { LoginForm } from "@/Components/Auth/LoginForm"
import { t } from "@/lib/i18n"

export default function Login({ canResetPassword = true, status = null }) {
    return (
        <>
            <Head>
                <title>{t("auth.login.headTitle")}</title>
                <meta
                    name="description"
                    content={t("auth.login.metaDescription")}
                />
                <link rel="canonical" href={route("login")} />
            </Head>

            <AuthShadcnShell
                imageAlt="Acceso seguro para negocios en Conecta HN"
                eyebrow={t("auth.login.eyebrow")}
                title={t("auth.login.heroTitle")}
                description={t("auth.login.heroDescription")}
            >
                <LoginForm canResetPassword={canResetPassword} status={status} />
            </AuthShadcnShell>
        </>
    )
}
