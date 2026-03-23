import { Head } from "@inertiajs/react"

import { SignupForm } from "@/Components/Auth/SignupForm"

export default function RegisterTenant(props) {
    return (
        <>
            <Head>
                <title>Registrar negocio</title>
                <meta
                    name="description"
                    content="Registra tu negocio en Conecta HN y crea la cuenta operativa conectada al panel real."
                />
                <link rel="canonical" href={route("tenant.register")} />
            </Head>

            <div className="flex min-h-svh w-full items-center justify-center bg-white p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <SignupForm {...props} />
                </div>
            </div>
        </>
    )
}
