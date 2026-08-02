import { Head } from "@inertiajs/react"

import { DovraProgram10Experience } from "@/Components/Dovra/DovraProgram10Experience"

export default function BuyerJourney(props) {
    return (
        <>
            <Head>
                <title>Buyer journey | Dovra AI</title>
                <meta
                    name="description"
                    content="Experiencia del comprador para revisar preparación, mercado y siguientes pasos con Dovra AI."
                />
            </Head>
            <DovraProgram10Experience {...props} initialScenarioKey="services" focusArea="buyer" />
        </>
    )
}
