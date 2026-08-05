export function resolveTenantVisual(tenant) {
    const directSource = [
        tenant?.hero_image,
        tenant?.banner_url,
        tenant?.cover_url,
        tenant?.logo_url,
    ].find((value) => typeof value === 'string' && value.trim() !== '');

    if (directSource) {
        return directSource;
    }

    const storedPath = [
        tenant?.banner_path,
        tenant?.cover_path,
        tenant?.logo_path,
    ].find((value) => typeof value === 'string' && value.trim() !== '');

    if (!storedPath) {
        return null;
    }

    if (storedPath.startsWith('http://') || storedPath.startsWith('https://') || storedPath.startsWith('/storage/')) {
        return storedPath;
    }

    return `/storage/${storedPath}`;
}

export function resolveTenantCityName(tenant) {
    if (typeof tenant?.city === 'string' && tenant.city.trim() !== '') {
        return tenant.city;
    }

    if (tenant?.city?.name) {
        return tenant.city.name;
    }

    return tenant?.location?.locality
        || tenant?.market?.country_code
        || 'Ubicación no configurada';
}

export function resolveTenantCategoryName(tenant) {
    if (typeof tenant?.category === 'string' && tenant.category.trim() !== '') {
        return tenant.category;
    }

    if (tenant?.category?.name) {
        return tenant.category.name;
    }

    return 'Negocio local';
}

export function resolveTenantDescription(tenant, fallback = 'Negocio local activo dentro de Dovra AI.') {
    if (typeof tenant?.description === 'string' && tenant.description.trim() !== '') {
        return tenant.description;
    }

    return fallback;
}

export function resolveTenantPlanLabel(tenant) {
    const labels = {
        semilla: 'Plan Semilla',
        crecimiento: 'Plan Crecimiento',
        cosecha: 'Plan Cosecha',
        pro: 'Plan Pro',
        enterprise: 'Enterprise',
    };

    if (tenant?.plan_type && labels[tenant.plan_type]) {
        return labels[tenant.plan_type];
    }

    return tenant?.is_pro ? 'Plan Pro' : 'Activo';
}

export function getTenantInitials(tenant) {
    const name = typeof tenant?.name === 'string' ? tenant.name.trim() : '';

    if (!name) {
        return 'CH';
    }

    return name
        .split(/\s+/)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join('');
}
