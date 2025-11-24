import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/Components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/Components/ui/popover';
import { MapPin, Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export default function LocationPicker({ cities = [], activeCity = null }) {
    const [open, setOpen] = useState(false);
    const safeCities = cities || [];

    const handleCitySelect = (cityId) => {
        setOpen(false);
        router.post(
            route('location.set'),
            { city_id: cityId },
            {
                preserveScroll: true,
                onSuccess: () => window.location.reload(),
            }
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="ghost"
                    role="combobox"
                    aria-expanded={open}
                    className="w-full md:w-[220px] justify-between hover:bg-muted/50 h-10 px-3 font-normal"
                >
                    <div className="flex items-center truncate gap-2">
                        <div className="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center shrink-0">
                            <MapPin className="h-4 w-4 text-primary" />
                        </div>
                        <div className="flex flex-col items-start text-xs">
                            <span className="text-muted-foreground">Ubicación</span>
                            <span className="font-medium text-foreground truncate max-w-[120px]">
                                {activeCity ? activeCity.name : 'Toda Honduras'}
                            </span>
                        </div>
                    </div>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[280px] p-0" align="end">
                <Command>
                    <CommandInput placeholder="Buscar ciudad..." />
                    <CommandList>
                        <CommandEmpty>No se encontró la ciudad.</CommandEmpty>
                        <CommandGroup heading="Ciudades Disponibles">
                            <CommandItem
                                value="all"
                                onSelect={() => {
                                    setOpen(false);
                                    router.post(route('location.clear'), {}, { onSuccess: () => window.location.reload() });
                                }}
                            >
                                <Check
                                    className={cn(
                                        'mr-2 h-4 w-4',
                                        !activeCity ? 'opacity-100' : 'opacity-0'
                                    )}
                                />
                                Toda Honduras
                            </CommandItem>
                            {safeCities.map((city) => (
                                <CommandItem
                                    key={city.id}
                                    value={city.name}
                                    onSelect={() => handleCitySelect(city.id)}
                                >
                                    <Check
                                        className={cn(
                                            'mr-2 h-4 w-4',
                                            activeCity?.id === city.id ? 'opacity-100' : 'opacity-0'
                                        )}
                                    />
                                    {city.name}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
