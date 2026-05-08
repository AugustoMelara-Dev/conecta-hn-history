import { Link, useForm } from "@inertiajs/react"
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
    const { data, setData, post, processing, errors, reset } = useForm({
        email: "",
        password: "",
        remember: false,
    })

    const submit = (event) => {
        event.preventDefault()

        post(route("login"), {
            onFinish: () => reset("password"),
        })
    }

    return (
        <Card className={cn("overflow-hidden shadow-sm", className)} {...props}>
            <CardHeader className="gap-3 border-b bg-muted/30">
                <Badge variant="secondary" className="w-fit gap-2">
                    <ShieldCheck aria-hidden="true" />
                    {t("auth.login.badge")}
                </Badge>
                <div>
                    <CardTitle className="text-2xl font-semibold">
                        {t("auth.login.title")}
                    </CardTitle>
                    <CardDescription className="mt-2">
                        {t("auth.login.description")}
                    </CardDescription>
                </div>
            </CardHeader>
            <CardContent className="p-5 sm:p-6">
                <Tabs defaultValue="password" className="w-full">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="password" className="gap-2">
                            <Building2 data-icon="inline-start" aria-hidden="true" />
                            {t("auth.login.passwordTab")}
                        </TabsTrigger>
                        <TabsTrigger value="quick" className="gap-2">
                            <Mail data-icon="inline-start" aria-hidden="true" />
                            {t("auth.login.quickTab")}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="password" className="mt-6">
                        <form onSubmit={submit} data-testid="login-form">
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
                                        autoFocus
                                        value={data.email}
                                        onChange={(event) => setData("email", event.target.value)}
                                        data-testid="login-email-input"
                                        aria-invalid={errors.email ? "true" : "false"}
                                        aria-describedby={errors.email ? "login-email-error" : undefined}
                                        className={cn(errors.email && "border-destructive/50 focus-visible:ring-destructive/20")}
                                    />
                                    {errors.email ? (
                                        <FieldDescription id="login-email-error" role="alert" className="text-destructive">
                                            {errors.email}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field data-invalid={errors.password ? "true" : undefined}>
                                    <div className="flex items-center">
                                        <FieldLabel htmlFor="password">{t("auth.login.password")}</FieldLabel>
                                        {canResetPassword ? (
                                            <Link
                                                href={route("password.request")}
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
                                        value={data.password}
                                        onChange={(event) => setData("password", event.target.value)}
                                        data-testid="login-password-input"
                                        aria-invalid={errors.password ? "true" : "false"}
                                        aria-describedby={errors.password ? "login-password-error" : undefined}
                                        className={cn(errors.password && "border-destructive/50 focus-visible:ring-destructive/20")}
                                    />
                                    {errors.password ? (
                                        <FieldDescription id="login-password-error" role="alert" className="text-destructive">
                                            {errors.password}
                                        </FieldDescription>
                                    ) : null}
                                </Field>

                                <Field>
                                    <Button type="submit" disabled={processing} data-testid="login-submit" className="w-full">
                                        {processing ? <Spinner data-icon="inline-start" /> : null}
                                        {processing ? t("auth.login.submitting") : t("auth.login.submit")}
                                    </Button>
                                    <FieldDescription className="text-center">
                                        {t("auth.login.noAccount")}{" "}
                                        <Link href={route("tenant.register")} className="font-medium underline underline-offset-4">
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
