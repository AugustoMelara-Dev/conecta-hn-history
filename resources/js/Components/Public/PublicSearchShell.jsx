import { Link } from '@inertiajs/react';
import { MapPin, Search, SlidersHorizontal } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';

export default function PublicSearchShell({
    searchValue = '',
    onSearchChange,
    searchId = 'public-search',
    searchName = 'search',
    searchPlaceholder = 'Buscar negocios, servicios o ubicaciones...',
    searchLabel = 'Buscar negocios, servicios o ubicaciones',
    searchTestId = undefined,
    cityValue = 'all',
    onCityChange,
    cities = [],
    cityId = 'public-city',
    cityPlaceholder = 'Todas las ciudades',
    cityAllLabel = 'Todas las ciudades',
    cityLabel = 'Ciudad',
    cityTriggerLabel = 'Filtrar por ciudad',
    cityTestId = undefined,
    onSubmit = undefined,
    formTestId = 'public-search-form',
    submitLabel = 'Buscar',
    submitTestId = undefined,
    showSubmit = false,
    showFiltersLink = false,
    filtersHref = null,
    filtersLabel = 'Filtros',
    showMobileFiltersButton = false,
    onMobileFiltersClick = undefined,
    mobileFiltersTestId = undefined,
    containerClassName = '',
    inputClassName = '',
    selectClassName = '',
    submitClassName = '',
    filtersClassName = '',
    mobileFiltersClassName = '',
}) {
    const content = (
        <div className={containerClassName}>
            <div className="relative">
                <label htmlFor={searchId} className="sr-only">
                    {searchLabel}
                </label>
                <Search className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                    id={searchId}
                    name={searchName}
                    type="text"
                    placeholder={searchPlaceholder}
                    value={searchValue}
                    onChange={(event) => onSearchChange?.(event.target.value)}
                    className={inputClassName}
                    data-testid={searchTestId}
                />
            </div>

            <div className="relative">
                <label htmlFor={cityId} className="sr-only">
                    {cityLabel}
                </label>
                <MapPin className="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                <Select value={cityValue} onValueChange={(value) => onCityChange?.(value)}>
                    <SelectTrigger
                        id={cityId}
                        aria-label={cityTriggerLabel}
                        className={selectClassName}
                        data-testid={cityTestId}
                    >
                        <SelectValue placeholder={cityPlaceholder} />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem value="all">{cityAllLabel}</SelectItem>
                            {cities.map((city) => (
                                <SelectItem key={city.id} value={city.slug}>{city.name}</SelectItem>
                            ))}
                        </SelectGroup>
                    </SelectContent>
                </Select>
            </div>

            {showSubmit ? (
                <Button
                    type="submit"
                    className={submitClassName}
                    data-testid={submitTestId}
                >
                    {submitLabel}
                </Button>
            ) : null}

            {showFiltersLink && filtersHref ? (
                <Button
                    asChild
                    variant="outline"
                    className={filtersClassName}
                >
                    <Link href={filtersHref}>
                        <SlidersHorizontal data-icon="inline-start" aria-hidden="true" />
                        {filtersLabel}
                    </Link>
                </Button>
            ) : null}

            {showMobileFiltersButton ? (
                <Button
                    type="button"
                    onClick={onMobileFiltersClick}
                    variant="outline"
                    className={mobileFiltersClassName}
                    data-testid={mobileFiltersTestId}
                >
                    <SlidersHorizontal data-icon="inline-start" aria-hidden="true" />
                    {filtersLabel}
                </Button>
            ) : null}
        </div>
    );

    if (!onSubmit) {
        return content;
    }

    return (
        <form onSubmit={onSubmit} data-testid={formTestId}>
            {content}
        </form>
    );
}
