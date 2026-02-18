<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CityEmployee;
use Illuminate\Support\Facades\File;

class CityEmployeeSeeder extends Seeder
{
    public function run()
    {
        $csvFile = database_path('data/city_employees.csv'); 

        if (!File::exists($csvFile)) {
            $this->command->error("CSV file not found at: $csvFile");
            return;
        }

        $data = array_map('str_getcsv', file($csvFile));

        foreach ($data as $row) {
            if (empty($row[1])) continue;

            CityEmployee::create([
                'id'        => $row[0], 
                'pmis_id'   => $row[1], 
                'full_name' => $row[2],
                'position'  => $row[3],
                'dept_id'   => $row[4], 
                'state'     => $row[5] ?? 1,
            ]);
        }
        
        $this->command->info('City Employees imported with positions successfully!');
    }
}