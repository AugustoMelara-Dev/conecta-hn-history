import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ConfirmPasswordForm } from "@/Components/Auth/ConfirmPasswordForm"

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar contraseña | Dovra AI" />

            <AuthShadcnShell
                imageAlt="Panel para confirmar contraseña"
                eyebrow="Paso de seguridad"
                title="Confirma que eres tu"
                description="Protegemos acciones sensibles pidiendo tu contraseña antes de continuar."
            >
                <ConfirmPasswordForm />
            </AuthShadcnShell>
        </>
    )
}
