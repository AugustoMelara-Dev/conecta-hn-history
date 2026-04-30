import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ConfirmPasswordForm } from "@/Components/Auth/ConfirmPasswordForm"

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar contraseña | Vito Business OS" />

            <AuthShadcnShell imageAlt="Panel para confirmar contraseña">
                <ConfirmPasswordForm />
            </AuthShadcnShell>
        </>
    )
}
