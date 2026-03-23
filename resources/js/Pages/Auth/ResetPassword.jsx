import { Head } from "@inertiajs/react"

import AuthShadcnShell from "@/Components/Auth/AuthShadcnShell"
import { ResetPasswordForm } from "@/Components/Auth/ResetPasswordForm"

export default function ResetPassword({ token, email }) {
    return (
        <>
            <Head title="Reset password" />

            <AuthShadcnShell imageAlt="Reset password panel">
                <ResetPasswordForm token={token} email={email} />
            </AuthShadcnShell>
        </>
    )
}
