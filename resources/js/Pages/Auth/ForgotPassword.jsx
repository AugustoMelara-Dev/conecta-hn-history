import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ForgotPasswordForm } from "@/Components/Auth/ForgotPasswordForm"
import { t } from "@/lib/i18n"

export default function ForgotPassword({ status = null }) {
    return (
        <>
            <Head title={t("auth.forgotPassword.headTitle")} />

            <AuthShadcnShell imageAlt={t("auth.forgotPassword.imageAlt")}>
                <ForgotPasswordForm status={status} />
            </AuthShadcnShell>
        </>
    )
}
