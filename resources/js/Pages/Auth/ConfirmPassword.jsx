import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ConfirmPasswordForm } from "@/Components/Auth/ConfirmPasswordForm"

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirmar contrasena | Conecta HN" />

            <AuthShadcnShell
                imageAlt="Panel para confirmar contrasena"
                eyebrow="Paso de seguridad"
                title="Confirma que eres tu"
                description="Protegemos acciones sensibles pidiendo tu contrasena antes de continuar."
            >
                <ConfirmPasswordForm />
            </AuthShadcnShell>
        </>
    )
}
