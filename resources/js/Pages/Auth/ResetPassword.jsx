import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ResetPasswordForm } from "@/Components/Auth/ResetPasswordForm"

export default function ResetPassword({ token, email }) {
    return (
        <>
            <Head>
                <title>Cambiar contraseña | Dovra AI</title>
                <meta
                    name="description"
                    content="Actualiza tu contraseña para recuperar acceso seguro a tu cuenta de Dovra AI."
                />
                <link rel="canonical" href={route("password.request")} />
            </Head>

            <AuthShadcnShell
                imageAlt="Panel para cambiar contraseña"
                eyebrow="Recuperacion segura"
                title="Vuelve a entrar sin perder el control"
                description="Actualiza tu contraseña desde un flujo protegido y regresa a tu cuenta cuando esté listo."
            >
                <ResetPasswordForm token={token} email={email} />
            </AuthShadcnShell>
        </>
    )
}
