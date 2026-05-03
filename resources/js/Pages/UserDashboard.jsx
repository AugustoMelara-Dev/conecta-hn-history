import { Head, Link } from '@inertiajs/react';
import { Heart, Shield, Store, TrendingUp, User } from 'lucide-react';

import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { EmptyState, MetricCard, PageHeader, ResponsiveContainer, SectionShell } from '@/Components/System';
import { t } from '@/lib/i18n';

export default function UserDashboard({ auth, favorites = [], followedTenants = [], analyticsSummary = null }) {
    const user = auth.user;
    const isSeller = user.role === 'seller';

    return (
        <>
            <Head title={t('dashboard.account.headTitleFull')} />

            <main className="min-h-screen bg-slate-50 text-slate-950">
                <PageHeader
                    title={t('dashboard.account.greeting', { name: user.name })}
                    subtitle={t('dashboard.account.fullSummary')}
                    sticky={false}
                    actions={(
                        <>
                            <Button asChild>
                                <Link href={route('directory.index')}>{t('dashboard.account.exploreDirectory')}</Link>
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={route('mi-cuenta.edit')}>{t('dashboard.account.editProfile')}</Link>
                            </Button>
                        </>
                    )}
                />

                <ResponsiveContainer size="5xl" paddedY className="space-y-6">
                    <SectionShell>
                        <div>
                            <p className="text-sm font-semibold uppercase tracking-wide text-slate-500">{t('dashboard.account.eyebrow')}</p>
                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-600">{user.email}</p>
                        </div>
                    </SectionShell>

                    <section className="grid gap-4 md:grid-cols-3">
                        <AccountCard icon={User} title={t('dashboard.account.profile')} description={user.email} href={route('mi-cuenta.edit')} />
                        <AccountCard icon={Heart} title={t('dashboard.account.favorites')} description={t('dashboard.account.favoritesCount', { count: favorites.length })} href={route('buyer.profile')} />
                        <AccountCard icon={Shield} title={t('dashboard.account.security')} description={t('dashboard.account.securityDescription')} href={route('mi-cuenta.edit')} />
                    </section>

                    <section className="grid gap-4 md:grid-cols-3">
                        <MetricCard title={t('dashboard.account.favorites')} value={favorites.length} icon={Heart} />
                        <MetricCard title={t('dashboard.account.followedBusinesses')} value={followedTenants.length} icon={Store} />
                        <MetricCard title={t('dashboard.account.businessSummary')} value={analyticsSummary?.visits ?? 0} icon={TrendingUp} />
                    </section>

                    <section className="grid gap-6 lg:grid-cols-2">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Heart className="h-5 w-5 text-slate-500" aria-hidden="true" />
                                    {t('dashboard.account.favoriteProducts')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {favorites.length > 0 ? (
                                    <div className="space-y-3">
                                        {favorites.map((item) => (
                                            <div key={item.id} className="rounded-lg border border-slate-200 p-3">
                                                <p className="font-semibold text-slate-900">{item.name}</p>
                                                <p className="text-sm text-slate-500">{item.business ?? item.tenant_name ?? t('dashboard.account.savedBusinessFallback')}</p>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        title={t('dashboard.account.noFavoritesTitle')}
                                        description={t('dashboard.account.noFavoritesDescription')}
                                        icon={Heart}
                                        actionLabel={t('dashboard.fallback.directoryAction')}
                                        actionHref={route('directory.index')}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <Store className="h-5 w-5 text-slate-500" aria-hidden="true" />
                                    {t('dashboard.account.followedBusinesses')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {followedTenants.length > 0 ? (
                                    <div className="space-y-3">
                                        {followedTenants.map((tenant) => (
                                            <div key={tenant.id} className="rounded-lg border border-slate-200 p-3">
                                                <p className="font-semibold text-slate-900">{tenant.name}</p>
                                                <p className="text-sm text-slate-500">{tenant.category_name ?? t('dashboard.account.followedBusinessFallback')}</p>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <EmptyState
                                        title={t('dashboard.account.noFollowingTitle')}
                                        description={t('dashboard.account.noFollowingDescription')}
                                        icon={Store}
                                        actionLabel={t('dashboard.account.exploreBusinesses')}
                                        actionHref={route('public.explore')}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    </section>

                    {isSeller ? (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-lg">
                                    <TrendingUp className="h-5 w-5 text-slate-500" aria-hidden="true" />
                                    {t('dashboard.account.businessSummary')}
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {analyticsSummary ? (
                                    <p className="text-sm leading-6 text-slate-600">
                                        {t('dashboard.account.visitsSummary', { visits: analyticsSummary.visits ?? 0 })}
                                    </p>
                                ) : (
                                    <EmptyState
                                        title={t('dashboard.account.noAnalyticsTitle')}
                                        description={t('dashboard.account.noAnalyticsDescription')}
                                        icon={TrendingUp}
                                    />
                                )}
                            </CardContent>
                        </Card>
                    ) : null}
                </ResponsiveContainer>
            </main>
        </>
    );
}

function AccountCard({ icon: Icon, title, description, href }) {
    return (
        <Link
            href={href}
            className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm transition hover:border-slate-300 hover:shadow"
        >
            <Icon className="h-5 w-5 text-slate-500" aria-hidden="true" />
            <p className="mt-3 font-semibold text-slate-950">{title}</p>
            <p className="mt-1 text-sm text-slate-500">{description}</p>
        </Link>
    );
}
