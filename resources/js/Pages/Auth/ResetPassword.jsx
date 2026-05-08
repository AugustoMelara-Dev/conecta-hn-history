import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ResetPasswordForm } from "@/Components/Auth/ResetPasswordForm"

export default function ResetPassword({ token, email }) {
    return (
        <>
            <Head title="Cambiar contrasena | Conecta HN" />

            <AuthShadcnShell
                imageAlt="Panel para cambiar contrasena"
                eyebrow="Recuperacion segura"
                title="Vuelve a entrar sin perder el control"
                description="Actualiza tu contrasena desde un flujo protegido y regresa a tu cuenta cuando este listo."
            >
                <ResetPasswordForm token={token} email={email} />
            </AuthShadcnShell>
        </>
    )
}
