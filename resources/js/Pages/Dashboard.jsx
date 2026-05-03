import { Head } from '@inertiajs/react';

import { EmptyState } from '@/Components/System';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { t } from '@/lib/i18n';
import { LayoutDashboard } from 'lucide-react';

export default function Dashboard() {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200">
                    {t('dashboard.fallback.header')}
                </h2>
            }
        >
            <Head title={t('dashboard.fallback.headTitle')} />

            <div className="py-12">
                <div className="mx-auto max-w-4xl sm:px-6 lg:px-8">
                    <EmptyState
                        icon={LayoutDashboard}
                        title={t('dashboard.fallback.title')}
                        description={t('dashboard.fallback.description')}
                        actionLabel={t('dashboard.fallback.directoryAction')}
                        actionHref={route('directory.index')}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
