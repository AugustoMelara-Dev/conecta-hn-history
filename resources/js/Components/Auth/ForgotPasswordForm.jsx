import { Link, useForm } from "@inertiajs/react"
import { MailCheck } from "lucide-react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
import { Button } from "@/Components/ui/button"
import {
    Field,
    FieldDescription,
    FieldGroup,
    FieldLabel,
    FieldSeparator,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { t } from "@/lib/i18n"
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
                    <h1 className="text-2xl font-bold text-slate-950" data-testid="forgot-password-heading">
                        {t("auth.forgotPassword.title")}
                    </h1>
                    <p className="text-sm text-balance text-slate-500">
                        {t("auth.forgotPassword.description")}
                    </p>
                </div>

                {status ? (
                    <Alert variant="success" aria-live="polite" data-testid="forgot-password-feedback">
                        <div className="flex gap-3">
                            <MailCheck className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                            <div>
                                <AlertTitle>{t("auth.forgotPassword.successTitle")}</AlertTitle>
                                <AlertDescription>{status}</AlertDescription>
                            </div>
                        </div>
                    </Alert>
                ) : null}

                <Field>
                    <FieldLabel htmlFor="email">{t("auth.forgotPassword.email")}</FieldLabel>
                    <Input
                        id="email"
                        type="email"
                        placeholder={t("auth.forgotPassword.placeholderEmail")}
                        autoComplete="email"
                        autoFocus
                        required
                        value={data.email}
                        onChange={(event) => setData("email", event.target.value)}
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={errors.email ? "forgot-password-email-error" : undefined}
                        data-testid="forgot-password-email-input"
                        className={cn(
                            inputClassName,
                            errors.email && "border-rose-300 focus-visible:ring-rose-200"
                        )}
                    />
                    {errors.email ? (
                        <FieldDescription id="forgot-password-email-error" className="text-rose-600">
                            {errors.email}
                        </FieldDescription>
                    ) : null}
                </Field>

                <Field>
                    <Button
                        type="submit"
                        disabled={processing}
                        data-testid="forgot-password-submit"
                        className="w-full bg-slate-950 text-white hover:bg-slate-800"
                    >
                        {processing ? <Spinner className="mr-2" /> : null}
                        {processing ? t("auth.forgotPassword.submitting") : t("auth.forgotPassword.submit")}
                    </Button>
                </Field>

                <FieldSeparator>{t("auth.forgotPassword.separator")}</FieldSeparator>

                <Field>
                    <Button
                        variant="outline"
                        type="button"
                        className="w-full border-slate-200 hover:bg-slate-50"
                        asChild
                    >
                        <Link href={route("login")}>{t("auth.forgotPassword.login")}</Link>
                    </Button>
                </Field>
            </FieldGroup>
        </form>
    )
}
