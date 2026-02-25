<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Departments

        $departments = [
            ['code' => 'OCM', 'name' => 'Office of the City Mayor'],
            ['code' => 'OSM', 'name' => 'Office for Strategy Management'],
            ['code' => 'HRM', 'name' => 'Human Resource Management'],
            ['code' => 'MARKET', 'name' => 'Market Operations'],
            ['code' => 'CVM', 'name' => 'Office of the City Vice Mayor'],
            ['code' => 'OSP', 'name' => 'Office of the Sangguniang Panlungsod'],
            ['code' => 'SSP', 'name' => 'Office of the Secretary to the Sangguniang Panlungsod'],
            ['code' => 'ADM', 'name' => 'Office of the City Administrator'],
            ['code' => 'CICTO', 'name' => 'City Information and Communication Tech Office'],
            ['code' => 'PDO', 'name' => 'Office of the City Planning and Development Coordinator'],
            ['code' => 'CBO', 'name' => 'Office of the City Budget Officer'],
            ['code' => 'ACA', 'name' => 'Office of the City Accountant'],
            ['code' => 'GSO', 'name' => 'Office of the City General Services Officer'],
            ['code' => 'CLO', 'name' => 'Office of the City Legal Officer'],
            ['code' => 'CTO', 'name' => 'Office of the City Treasurer'],
            ['code' => 'CHO', 'name' => 'Office of the City Health Officer'],
            ['code' => 'OCA', 'name' => 'Office of the City Assessor'],
            ['code' => 'SWD', 'name' => 'Office of the City Social Welfare and Development Officer'],
            ['code' => 'REG', 'name' => 'Office of the City Civil Registrar'],
            ['code' => 'AGR', 'name' => 'Office of the City Agriculturist'],
            ['code' => 'ENR', 'name' => 'Office of the City Environment and Natural Resources'],
            ['code' => 'VET', 'name' => 'Office of the City Veterinarian'],
            ['code' => 'OCE', 'name' => 'Office of the City Engineer'],
            ['code' => 'OPS', 'name' => 'Office for Public Safety'],
            ['code' => 'LEBDO', 'name' => 'Local Economic and Business Dev\'t Office'],
            ['code' => 'ELEC', 'name' => 'Electrical Section'],
            ['code' => 'LIB', 'name' => 'City Library'],
            ['code' => 'PIO', 'name' => 'Public Information Office'],
            ['code' => 'OSCA', 'name' => 'Office of the Senior Citizens Affair'],
            ['code' => 'CDRRM', 'name' => 'City Disaster Risk Reduction and Management'],
            ['code' => 'PESO', 'name' => 'Public Employment Service Office'],
            ['code' => 'LYDO', 'name' => 'Youth Development Office'],
            ['code' => 'COOP', 'name' => 'Cooperative Development Office'],
            ['code' => 'GAD', 'name' => 'Gender and Development Office'],
            ['code' => 'PDAO', 'name' => 'Person with Disability Affairs Office'],
            ['code' => 'OIA', 'name' => 'Office for Internal Audit'],
            ['code' => 'ABEO', 'name' => 'Agricultural Biosystem Engineering Office'],
            ['code' => 'SPO', 'name' => 'Special\'s Project Office'],
            ['code' => 'LIGA', 'name' => 'Liga ng mga Barangay'],
            ['code' => 'BAC', 'name' => 'Bids and Awards Committee'],
            ['code' => 'DILG', 'name' => 'Department of the Interior and Local Government'],
        ];

        foreach ($departments as $dept) {
            Department::firstOrCreate(['code' => $dept['code']], $dept);
        }

        // 2. Statuses

        $statuses = [
            ['name' => 'Active'],
            ['name' => 'Amended'],
            ['name' => 'Superseded'],
            ['name' => 'Repealed'],
            ['name' => 'Suspended'],
        ];

        DB::table('statuses')->insert($statuses);   

        // 3. Users

        $users = [
            [
                'name' => 'System Admin', 
                'email' => 'admin@gmail.com', 
                'role' => 'system_admin',
                'department_id' => 10 
            ],
            [
                'name' => 'Supervisor User', 
                'email' => 'supervisor@gmail.com', 
                'role' => 'supervisor', 
                'department_id' => 9
            ],
            [
                'name' => 'Focal Person (Tourism)', 
                'email' => 'focal@gmail.com', 
                'role' => 'focal_person', 
                'department_id' => 1 
            ],
            [
                'name' => 'Monitoring Committee', 
                'email' => 'rrt@gmail.com', 
                'role' => 'monitoring_committee',
                'department_id' => 1
            ],
            [
                'name' => 'Public User', 
                'email' => 'guest@gmail.com', 
                'role' => 'read_only',
                'department_id' => null
            ],
            [
                'name' => 'Kent Aldrin Tan', 
                'email' => 'kntaldrntn@gmail.com', 
                'role' => 'system_admin',
                'department_id' => 10 
            ],
        ];

        foreach ($users as $data) {
            User::factory()->create($data);
        }
    }
}
