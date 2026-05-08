import { Link, useForm } from "@inertiajs/react"
import { MailCheck } from "lucide-react"

import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert"
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
    FieldLabel,
    FieldSeparator,
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { t } from "@/lib/i18n"
import { cn } from "@/lib/utils"

export function ForgotPasswordForm({ status = null, className, ...props }) {
    const { data, setData, post, processing, errors } = useForm({
        email: "",
    })

    const submit = (event) => {
        event.preventDefault()
        post(route("password.email"))
    }

    return (
        <Card className={cn("overflow-hidden shadow-sm", className)} {...props}>
            <CardHeader className="gap-2 border-b bg-muted/30 text-center">
                <CardTitle data-testid="forgot-password-heading">
                    {t("auth.forgotPassword.title")}
                </CardTitle>
                <CardDescription>{t("auth.forgotPassword.description")}</CardDescription>
            </CardHeader>

            <CardContent className="p-5 sm:p-6">
                <form onSubmit={submit}>
                    <FieldGroup>
                        {status ? (
                            <Alert variant="success" aria-live="polite" data-testid="forgot-password-feedback">
                                <MailCheck aria-hidden="true" />
                                <AlertTitle>{t("auth.forgotPassword.successTitle")}</AlertTitle>
                                <AlertDescription>{status}</AlertDescription>
                            </Alert>
                        ) : null}

                        <Field data-invalid={Boolean(errors.email)}>
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
                                    errors.email && "border-destructive/50 focus-visible:ring-destructive/20"
                                )}
                            />
                            {errors.email ? (
                                <FieldDescription id="forgot-password-email-error" role="alert" className="text-destructive">
                                    {errors.email}
                                </FieldDescription>
                            ) : null}
                        </Field>

                        <Field>
                            <Button
                                type="submit"
                                disabled={processing}
                                data-testid="forgot-password-submit"
                                className="w-full"
                            >
                                {processing ? <Spinner data-icon="inline-start" /> : null}
                                {processing ? t("auth.forgotPassword.submitting") : t("auth.forgotPassword.submit")}
                            </Button>
                        </Field>

                        <FieldSeparator>{t("auth.forgotPassword.separator")}</FieldSeparator>

                        <Field>
                            <Button
                                variant="outline"
                                type="button"
                                className="w-full"
                                asChild
                            >
                                <Link href={route("login")}>{t("auth.forgotPassword.login")}</Link>
                            </Button>
                        </Field>
                    </FieldGroup>
                </form>
            </CardContent>
        </Card>
    )
}
