import { Link, usePage } from "@inertiajs/react"
import { useMemo, useState } from "react"
import { Building2, Mail, ShieldCheck } from "lucide-react"

import { QuickAccessForm } from "@/Components/Auth/QuickAccessForm"
import { Alert, AlertDescription } from "@/Components/ui/alert"
import { Badge } from "@/Components/ui/badge"
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
} from "@/Components/ui/field"
import { Input } from "@/Components/ui/input"
import { Spinner } from "@/Components/ui/spinner"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs"
import { t } from "@/lib/i18n"
import { cn } from "@/lib/utils"

export function LoginForm({
    canResetPassword = true,
    status = null,
    className,
    ...props
}) {
    const { props: pageProps } = usePage()
    const errors = pageProps.errors ?? {}
    const [processing, setProcessing] = useState(false)
    const csrfToken = useMemo(
        () => document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") ?? "",
        []
    )

    return (
        <Card className={cn("min-w-0 overflow-hidden border-border/70 [overflow-wrap:anywhere] shadow-[0_24px_80px_rgba(15,23,42,0.08)]", className)} {...props}>
            <CardHeader className="min-w-0 gap-3 border-b bg-background">
                <Badge variant="outline" className="w-fit gap-2 rounded-full">
                    <ShieldCheck aria-hidden="true" />
                    {t("auth.login.badge")}
                </Badge>
                <div className="min-w-0">
                    <CardTitle className="font-display text-3xl font-bold">
                        {t("auth.login.title")}
                    </CardTitle>
                    <CardDescription className="mt-2">
                        {t("auth.login.description")}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="p-5 sm:p-6">
                <Tabs defaultValue="password" className="min-w-0 w-full">
                    <TabsList className="grid h-auto min-h-11 w-full min-w-0 grid-cols-2 rounded-xl bg-muted">
                        <TabsTrigger value="password" className="min-w-0 whitespace-normal py-2 text-center leading-tight">
                            <Building2 data-icon="inline-start" aria-hidden="true" />
                            {t("auth.login.passwordTab")}
                        </TabsTrigger>
                        <TabsTrigger value="quick" className="min-w-0 whitespace-normal py-2 text-center leading-tight">
                            <Mail data-icon="inline-start" aria-hidden="true" />
                            {t("auth.login.quickTab")}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="password" className="mt-6">
                        <form method="post" action="/login" onSubmit={() => setProcessing(true)} data-testid="login-form">
                            <input type="hidden" name="_token" value={csrfToken} />
                            <FieldGroup>
                                {status ? (
                                    <Alert>
                                        <AlertDescription>{status}</AlertDescription>
                                    </Alert>
                                ) : null}

                                <Field data-invalid={errors.email ? "true" : undefined}>
                                    <FieldLabel htmlFor="email">{t("auth.login.email")}</FieldLabel>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        placeholder={t("auth.login.placeholderEmail")}
                                        autoComplete="email"
                                        spellCheck={false}
                                        required
                                        data-testid="login-email-input"
                                        aria-invalid={errors.email ? "true" : "false"}
                                        aria-describedby={errors.email ? "login-email-error" : undefined}
                                        className={cn("h-12 rounded-xl bg-muted/60", errors.email && "border-destructive/50 focus-visible:ring-destructive/20")}
                                    />
                                    {errors.email ? (
                                        <FieldDescription id="login-email-error" role="alert" className="text-destructive">
                                            {errors.email}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field data-invalid={errors.password ? "true" : undefined}>
                                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                        <FieldLabel htmlFor="password">{t("auth.login.password")}</FieldLabel>
                                        {canResetPassword ? (
                                            <Link
                                                href="/forgot-password"
                                                className="ml-auto text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                            >
                                                {t("auth.login.forgotPassword")}
                                            </Link>
                                        ) : null}
                                    </div>
                                    <Input
                                        id="password"
                                        name="password"
                                        type="password"
                                        autoComplete="current-password"
                                        required
                                        data-testid="login-password-input"
                                        aria-invalid={errors.password ? "true" : "false"}
                                        aria-describedby={errors.password ? "login-password-error" : undefined}
                                        className={cn("h-12 rounded-xl bg-muted/60", errors.password && "border-destructive/50 focus-visible:ring-destructive/20")}
                                    />
                                    {errors.password ? (
                                        <FieldDescription id="login-password-error" role="alert" className="text-destructive">
                                            {errors.password}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field>
                                    <Button type="submit" disabled={processing} data-testid="login-submit" className="h-12 w-full rounded-xl">
                                        {processing ? <Spinner data-icon="inline-start" /> : null}
                                        {processing ? t("auth.login.submitting") : t("auth.login.submit")}
                                    </Button>
                                    <FieldDescription className="text-center">
                                        {t("auth.login.noAccount")}{" "}
                                        <Link href="/registrar-negocio" className="font-medium underline underline-offset-4">
                                            {t("auth.login.registerBusiness")}
                                        </Link>
                                    </FieldDescription>
                                </Field>
                            </FieldGroup>
                        </form>
                    </TabsContent>

                    <TabsContent value="quick" className="mt-6">
                        <QuickAccessForm />
                    </TabsContent>
                </Tabs>
            </CardContent>
        </Card>
    )
}
