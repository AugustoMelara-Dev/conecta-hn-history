import { Head, Link, useForm, usePage } from "@inertiajs/react"
import { RefreshCcw } from "lucide-react"

import { Button } from "@/Components/ui/button"
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from "@/Components/ui/card"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldSeparator,
} from "@/Components/ui/field"

export default function VerifyEmail({ status }) {
    const { auth } = usePage().props
    const { post, processing } = useForm({})

    const submit = (event) => {
        event.preventDefault()
        post(route("verification.send"))
    }

    return (
        <>
            <Head title="Verify your email" />

            <div className="flex min-h-svh w-full items-center justify-center bg-white p-6 md:p-10">
                <div className="w-full max-w-sm">
                    <Card className="border-slate-200 bg-white text-slate-950 shadow-sm">
                        <CardHeader>
                            <CardTitle className="text-2xl font-semibold text-slate-950">
                                Verify your email
                            </CardTitle>
                            <CardDescription className="text-slate-500">
                                We sent a verification link to{" "}
                                <span className="font-medium text-slate-950">
                                    {auth?.user?.email}
                                </span>
                                . Open that email to unlock full access to your account.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <FieldGroup>
                                {status === "verification-link-sent" ? (
                                    <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                                        A new verification link has been sent to your email address.
                                    </div>
                                ) : null}

                                <Field>
                                    <form onSubmit={submit}>
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full bg-slate-950 text-white hover:bg-slate-800"
                                        >
                                            <RefreshCcw
                                                className={`mr-2 h-4 w-4 ${processing ? "animate-spin" : ""}`}
                                            />
                                            {processing ? "Sending..." : "Resend verification email"}
                                        </Button>
                                    </form>
                                </Field>

                                <FieldSeparator>Need a different account?</FieldSeparator>

                                <Field>
                                    <Button
                                        variant="outline"
                                        type="button"
                                        className="w-full border-slate-200 bg-white text-slate-950 hover:bg-slate-50"
                                        asChild
                                    >
                                        <Link href={route("logout")} method="post" as="button">
                                            Sign out
                                        </Link>
                                    </Button>
                                    <FieldDescription className="text-center">
                                        Verify your email first to continue into the app.
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    )
}
