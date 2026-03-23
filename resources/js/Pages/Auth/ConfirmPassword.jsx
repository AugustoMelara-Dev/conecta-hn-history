import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ConfirmPasswordForm } from "@/Components/Auth/ConfirmPasswordForm"

export default function ConfirmPassword() {
    return (
        <>
            <Head title="Confirm password" />

            <AuthShadcnShell imageAlt="Confirm password panel">
                <ConfirmPasswordForm />
            </AuthShadcnShell>
        </>
    )
}
