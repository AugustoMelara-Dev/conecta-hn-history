import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ForgotPasswordForm } from "@/Components/Auth/ForgotPasswordForm"

export default function ForgotPassword({ status = null }) {
    return (
        <>
            <Head title="Recuperar acceso | Vito Business OS" />

            <AuthShadcnShell imageAlt="Panel de recuperación de acceso">
                <ForgotPasswordForm status={status} />
            </AuthShadcnShell>
        </>
    )
}
