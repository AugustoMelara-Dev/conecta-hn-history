import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ForgotPasswordForm } from "@/Components/Auth/ForgotPasswordForm"

export default function ForgotPassword({ status = null }) {
    return (
        <>
            <Head title="Forgot password" />

            <AuthShadcnShell imageAlt="Password recovery panel">
                <ForgotPasswordForm status={status} />
            </AuthShadcnShell>
        </>
    )
}
