import { Head, Link } from '@inertiajs/react';
import { Heart, Shield, Store, User } from 'lucide-react';

import { EmptyState } from '@/Components/Feedback/UXStates';
import { Button } from '@/Components/ui/button';
import { t } from '@/lib/i18n';

export default function UserDashboard({ auth }) {
    const user = auth.user;

    return (
        <>
            <Head title={t('dashboard.account.headTitle')} />

            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-950 sm:px-6 lg:px-8">
                <div className="mx-auto max-w-4xl space-y-6">
                    <header className="flex flex-col gap-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dashboard.account.eyebrow')}</p>
                            <h1 className="mt-2 text-2xl font-bold text-slate-950">{t('dashboard.account.greeting', { name: user.name })}</h1>
                            <p className="mt-1 text-sm text-slate-600">
                                {t('dashboard.account.summary')}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            <Button asChild>
                                <Link href={route('directory.index')}>{t('dashboard.account.exploreDirectory')}</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('mi-cuenta.edit')}>{t('dashboard.account.editProfile')}</Link>
                            </Button>
                        </div>
                    </header>

                    <section className="grid gap-4 md:grid-cols-3">
                        <AccountAction icon={User} title={t('dashboard.account.profile')} href={route('mi-cuenta.edit')} />
                        <AccountAction icon={Heart} title={t('dashboard.account.favorites')} href={route('buyer.profile')} />
                        <AccountAction icon={Shield} title={t('dashboard.account.security')} href={route('mi-cuenta.edit')} />
                    </section>

                    <EmptyState
                        icon={Store}
                        eyebrow={t('dashboard.account.activityEyebrow')}
                        title={t('dashboard.account.noActivityTitle')}
                        description={t('dashboard.account.noActivityDescription')}
                        actionLabel={t('dashboard.account.viewBusinesses')}
                        actionHref={route('directory.index')}
                    />
                </div>
            </main>
        </>
    );
}

function AccountAction({ icon: Icon, title, href }) {
    return (
        <Link
            href={href}
            className="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-slate-300 hover:text-slate-950"
        >
            <Icon className="h-5 w-5 text-slate-500" aria-hidden="true" />
            {title}
        </Link>
    );
}
