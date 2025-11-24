<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GeoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Honduras Political Structure
        $structure = [
            'Cortés' => [
                'San Pedro Sula' => ['lat' => 15.5045, 'lng' => -88.0251],
                'Choloma' => ['lat' => 15.6139, 'lng' => -87.9531],
                'La Lima' => ['lat' => 15.4377, 'lng' => -87.9107],
                'Villanueva' => ['lat' => 15.3189, 'lng' => -88.0014],
            ],
            'Francisco Morazán' => [
                'Tegucigalpa' => ['lat' => 14.0723, 'lng' => -87.1921],
                'Valle de Ángeles' => ['lat' => 14.1667, 'lng' => -87.0333],
                'Santa Lucía' => ['lat' => 14.1333, 'lng' => -87.1167],
            ],
            'Atlántida' => [
                'La Ceiba' => ['lat' => 15.7659, 'lng' => -86.7961],
                'Tela' => ['lat' => 15.7797, 'lng' => -87.4556],
                'El Porvenir' => ['lat' => 15.5833, 'lng' => -87.1833],
            ],
            'Colón' => [
                'Trujillo' => ['lat' => 15.9242, 'lng' => -85.9575],
                'Tocoa' => ['lat' => 15.6500, 'lng' => -86.0000],
            ],
            'Yoro' => [
                'Yoro' => ['lat' => 15.1333, 'lng' => -87.1333],
                'El Progreso' => ['lat' => 15.4000, 'lng' => -87.8000],
                'Olanchito' => ['lat' => 15.4833, 'lng' => -86.5667],
            ],
            'Choluteca' => [
                'Choluteca' => ['lat' => 13.3061, 'lng' => -87.1906],
            ],
            'Olancho' => [
                'Juticalpa' => ['lat' => 14.6656, 'lng' => -86.2231],
                'Catacamas' => ['lat' => 14.8500, 'lng' => -85.9000],
            ],
            'Copán' => [
                'Santa Rosa de Copán' => ['lat' => 14.7667, 'lng' => -88.7833],
            ],
        ];

        foreach ($structure as $departmentName => $cities) {
            $department = Department::create([
                'name' => $departmentName,
                'slug' => Str::slug($departmentName),
            ]);

            // For simplicity, each city is its own municipality
            // In reality, municipalities can have multiple cities
            foreach ($cities as $cityName => $coordinates) {
                $municipality = Municipality::create([
                    'department_id' => $department->id,
                    'name' => $cityName, // Municipality name same as city for simplicity
                    'slug' => Str::slug($cityName),
                ]);

                City::create([
                    'municipality_id' => $municipality->id,
                    'name' => $cityName,
                    'slug' => Str::slug($cityName),
                    'coordinates' => $coordinates,
                ]);
            }
        }

        $this->command->info('✅ Honduras geospatial data seeded successfully!');

        // OPTION 1: Migrate existing tenants and products to San Pedro Sula
        $sanPedroSula = City::where('slug', 'san-pedro-sula')->first();

        if ($sanPedroSula) {
            \DB::table('tenants')
                ->whereNull('city_id')
                ->update(['city_id' => $sanPedroSula->id]);

            \DB::table('products')
                ->whereNull('city_id')
                ->update(['city_id' => $sanPedroSula->id]);

            $this->command->info('✅ Existing data migrated to San Pedro Sula (city_id: '.$sanPedroSula->id.')');
        }
    }
}
