import { Head } from "@inertiajs/react"

import { LoginForm } from "@/Components/Auth/LoginForm"

export default function Login({ canResetPassword = true, status = null }) {
    return (
        <>
            <Head>
                <title>Iniciar sesion</title>
                <meta
                    name="description"
                    content="Accede al panel de Conecta HN para administrar tu negocio."
                />
                <link rel="canonical" href={route("login")} />
            </Head>

            <div className="flex min-h-svh w-full items-center justify-center bg-white p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <LoginForm canResetPassword={canResetPassword} status={status} />
                </div>
            </div>
        </>
    )
}
