import React, { useState, useEffect } from 'react';
import { MapPin, Check, Search, Building2, Factory, Palmtree, Church, Sun, Waves } from 'lucide-react';
import { router } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/Components/ui/dialog";
import { Input } from '@/Components/ui/input';
import { cn } from '@/lib/utils';

export default function LocationPicker({ defaultCity = 'Tegucigalpa' }) {
    const [open, setOpen] = useState(false);
    const [selectedCity, setSelectedCity] = useState(defaultCity);
    const [searchQuery, setSearchQuery] = useState('');

    const cities = [
        { name: 'Tegucigalpa', icon: Building2, color: 'text-slate-700' },
        { name: 'San Pedro Sula', icon: Factory, color: 'text-blue-600' },
        { name: 'La Ceiba', icon: Palmtree, color: 'text-emerald-600' },
        { name: 'Comayagua', icon: Church, color: 'text-amber-700' },
        { name: 'Choluteca', icon: Sun, color: 'text-orange-500' },
        { name: 'Roatán', icon: Waves, color: 'text-cyan-500' },
    ];

    useEffect(() => {
        const savedCity = localStorage.getItem('conecta_city');
        if (savedCity) {
            setSelectedCity(savedCity);
        } else {
            setOpen(true);
        }
    }, []);

    const handleOpenChange = (newOpen) => {
        // Prevent closing if no city is selected
        if (!newOpen && !localStorage.getItem('conecta_city')) {
            return;
        }
        setOpen(newOpen);
    };

    const handleSelectCity = (city) => {
        setSelectedCity(city);
        localStorage.setItem('conecta_city', city);
        setOpen(false);

        // Reload page with new city filter
        router.visit(window.location.pathname, {
            data: { city: city },
            preserveScroll: true,
            preserveState: true,
            only: ['tenants', 'featuredTenants'],
        });
    };

    const filteredCities = cities.filter(city =>
        city.name.toLowerCase().includes(searchQuery.toLowerCase())
    );

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    size="sm"
                    className="rounded-full border-slate-200 bg-slate-50/50 hover:bg-slate-100 text-slate-700 gap-2"
                >
                    <MapPin className="h-3.5 w-3.5 text-primary" />
                    <span className="hidden sm:inline">Ubicación:</span>
                    <span className="font-medium">{selectedCity}</span>
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md bg-white z-[200] shadow-2xl border border-slate-200">
                <DialogHeader>
                    <DialogTitle>Elige tu ciudad</DialogTitle>
                    <DialogDescription>
                        Te mostraremos los negocios disponibles en tu área.
                    </DialogDescription>
                </DialogHeader>

                <div className="relative mb-4">
                    <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                    <Input
                        placeholder="Buscar ciudad..."
                        className="pl-9"
                        value={searchQuery}
                        onChange={(e) => setSearchQuery(e.target.value)}
                    />
                </div>

                <div className="grid gap-2 max-h-[300px] overflow-y-auto pr-2">
                    {filteredCities.map((city) => {
                        const IconComponent = city.icon;
                        return (
                            <div
                                key={city.name}
                                onClick={() => handleSelectCity(city.name)}
                                className={cn(
                                    "flex items-center justify-between p-3.5 rounded-xl cursor-pointer transition-all",
                                    selectedCity === city.name
                                        ? "bg-slate-100 border-2 border-slate-900"
                                        : "hover:bg-slate-50 border-2 border-transparent hover:border-slate-200"
                                )}
                            >
                                <div className="flex items-center gap-3">
                                    <div className={cn("w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center")}>
                                        <IconComponent className={cn("h-5 w-5", city.color)} />
                                    </div>
                                    <span className={cn(
                                        "font-medium text-base",
                                        selectedCity === city.name ? "text-slate-900" : "text-slate-700"
                                    )}>
                                        {city.name}
                                    </span>
                                </div>
                                {selectedCity === city.name && (
                                    <Check className="h-5 w-5 text-slate-900" />
                                )}
                            </div>
                        );
                    })}
                    {filteredCities.length === 0 && (
                        <div className="text-center py-8 text-muted-foreground text-sm">
                            No encontramos esa ciudad.
                        </div>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
