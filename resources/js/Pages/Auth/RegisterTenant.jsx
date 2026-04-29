import { Head } from "@inertiajs/react"

import { RegisterTenantWizard } from "@/Components/Auth/RegisterTenantWizard"

export default function RegisterTenant(props) {
    return (
        <>
            <Head>
                <title>Registrar negocio</title>
                <meta
                    name="description"
                    content="Registra tu negocio en Vito Business OS y arranca con el flujo correcto para tiendas, restaurantes, servicios o modelos mixtos."
                />
                <link rel="canonical" href={route("tenant.register")} />
            </Head>

            <RegisterTenantWizard {...props} />
        </>
    )
}
