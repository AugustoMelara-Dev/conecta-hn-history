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

    const handleCitySelect = (cityId) => {
        setOpen(false);

        router.post(
            route('location.set'),
            { city_id: cityId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    // Optional: Show toast notification
                },
            }
        );
    };

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    className="w-[200px] justify-between"
                >
                    <MapPin className="mr-2 h-4 w-4 shrink-0" />
                    <span className="truncate">
                        {activeCity ? activeCity.name : 'Seleccionar Ciudad'}
                    </span>
                </Button>
            </PopoverTrigger>
            <PopoverContent className="w-[300px] p-0">
                <Command>
                    <CommandInput placeholder="Buscar ciudad..." />
                    <CommandList>
                        <CommandEmpty>No se encontró la ciudad.</CommandEmpty>
                        <CommandGroup>
                            {cities.map((city) => (
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
