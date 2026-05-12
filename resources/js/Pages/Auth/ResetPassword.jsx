import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ResetPasswordForm } from "@/Components/Auth/ResetPasswordForm"

export default function ResetPassword({ token, email }) {
    return (
        <>
            <Head>
                <title>Cambiar contrasena | Vito Business OS</title>
                <meta
                    name="description"
                    content="Actualiza tu contrasena para recuperar acceso seguro a tu cuenta de Vito Business OS."
                />
                <link rel="canonical" href={route("password.request")} />
            </Head>

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
