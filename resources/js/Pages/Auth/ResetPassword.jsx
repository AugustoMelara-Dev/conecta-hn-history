import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ResetPasswordForm } from "@/Components/Auth/ResetPasswordForm"

export default function ResetPassword({ token, email }) {
    return (
        <>
            <Head title="Cambiar contraseña | Conecta HN" />

            <AuthShadcnShell imageAlt="Panel para cambiar contraseña">
                <ResetPasswordForm token={token} email={email} />
            </AuthShadcnShell>
        </>
    )
}
