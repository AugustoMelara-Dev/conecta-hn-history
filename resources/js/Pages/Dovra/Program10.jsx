import { Head } from "@inertiajs/react"

import { DovraProgram10Experience } from "@/Components/Dovra/DovraProgram10Experience"
import { PUBLIC_BRAND } from "@/lib/publicBrand"

export default function Program10(props) {
    return (
        <>
            <Head>
                <title>Programa 10 | Dovra AI</title>
                <meta
                    name="description"
                    content={`Vista previa de ${PUBLIC_BRAND.name} con onboarding adaptativo, buyer prep y accesibilidad semántica.`}
                />
            </Head>
            <DovraProgram10Experience {...props} />
        </>
    )
}
