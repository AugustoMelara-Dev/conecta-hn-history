import { Link, useForm } from "@inertiajs/react"

import { Button } from "@/Components/ui/button"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldSeparator,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { cn } from "@/lib/utils"

export function ForgotPasswordForm({ status = null, className, ...props }) {
    const inputClassName =
        "h-10 border-slate-200 bg-white text-slate-950 placeholder:text-slate-400 focus-visible:ring-slate-300 focus-visible:ring-offset-0"

    const { data, setData, post, processing, errors } = useForm({
        email: "",
    })

    const submit = (event) => {
        event.preventDefault()
        post(route("password.email"))
    }

    return (
        <form onSubmit={submit} className={cn("flex flex-col gap-6", className)} {...props}>
            <FieldGroup>
                <div className="flex flex-col items-center gap-1 text-center">
                    <h1 className="text-2xl font-bold text-slate-950">Forgot your password?</h1>
                    <p className="text-sm text-balance text-slate-500">
                        Enter your email below and we&apos;ll send you a link to reset your password.
                    </p>
                </div>

                {status ? (
                    <div className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">
                        {status}
                    </div>
                ) : null}

                <Field>
                    <FieldLabel htmlFor="email">Email</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        placeholder="m@example.com"
                        autoComplete="email"
                        autoFocus
                        required
                        value={data.email}
                        onChange={(event) => setData("email", event.target.value)}
                        className={cn(
                            inputClassName,
                            errors.email && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.email ? (
                        <FieldDescription className="text-rose-600">{errors.email}</FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="w-full bg-slate-950 text-white hover:bg-slate-800"
                    >
                        {processing ? "Sending..." : "Send reset link"}
                    </Button>
                </Field>

                <FieldSeparator>Back to access</FieldSeparator>

                <Field>
                    <Button
                        variant="outline"
                        type="button"
                        className="w-full border-slate-200 hover:bg-slate-50"
                        asChild
                    >
                        <Link href={route("login")}>Sign in</Link>
                    </Button>
                </Field>
            </FieldGroup>
        </form>
    )
}
