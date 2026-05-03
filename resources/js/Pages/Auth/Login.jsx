import { Head, Link } from "@inertiajs/react"
import { ArrowLeft, Layers3 } from "lucide-react"

import { LoginForm } from "@/Components/Auth/LoginForm"
import { t } from "@/lib/i18n"

export default function Login({ canResetPassword = true, status = null }) {
    const proofItems = [
        t("auth.login.proofBusinessPanel"),
        t("auth.login.proofQuickAccess"),
        t("auth.login.proofVerifiedEmail"),
    ]

    return (
        <>
            <Head>
                <title>{t("auth.login.headTitle")}</title>
                <meta
                    name="description"
                    content={t("auth.login.metaDescription")}
                />
                <link rel="canonical" href={route("login")} />
            </Head>

            <main className="min-h-svh bg-[#f7f8fb] text-slate-950">
                <div className="mx-auto grid min-h-svh w-full max-w-6xl grid-cols-1 lg:grid-cols-[0.95fr_1.05fr]">
                    <section className="hidden border-r border-slate-200 bg-white p-10 lg:flex lg:flex-col lg:justify-between">
                        <Link href={route("home")} className="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-950">
                            <ArrowLeft className="h-4 w-4" />
                            {t("auth.login.backToSite")}
                        </Link>

                        <div className="space-y-8">
                            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-950 text-white">
                                <Layers3 className="h-7 w-7" />
                            </div>
                            <div className="space-y-4">
                                <p className="text-sm font-semibold uppercase tracking-wide text-emerald-700">
                                    {t("auth.login.eyebrow")}
                                </p>
                                <h1 className="max-w-md text-4xl font-semibold leading-tight tracking-normal text-slate-950">
                                    {t("auth.login.heroTitle")}
                                </h1>
                                <p className="max-w-md text-base leading-7 text-slate-600">
                                    {t("auth.login.heroDescription")}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-3 text-sm">
                            {proofItems.map((item) => (
                                <div key={item} className="rounded-lg border border-slate-200 p-3 font-medium text-slate-700">
                                    {item}
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="flex items-center justify-center p-5 sm:p-8">
                        <div className="w-full max-w-md">
                            <div className="mb-6 flex items-center justify-between lg:hidden">
                                <Link href={route("home")} className="inline-flex items-center gap-2 text-sm font-medium text-slate-600">
                                    <ArrowLeft className="h-4 w-4" />
                                    {t("auth.login.mobileHome")}
                                </Link>
                                <span className="text-sm font-semibold text-slate-950">Vito Business OS</span>
                            </div>
                            <LoginForm canResetPassword={canResetPassword} status={status} />
                        </div>
                    </section>
                </div>
            </main>
        </>
    )
}
