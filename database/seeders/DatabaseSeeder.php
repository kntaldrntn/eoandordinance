<?php

namespace Database\Seeders;

use App\Models\CommitteeRegistry;
use App\Models\OrdinanceCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Committee Registries
        $committees = [
            'Committee on Cooperatives',
            'Committee on Environment',
            'Committee on Finance, Budget and Appropriations',
            'Committee on Human Rights',
            'Committee on Ways and Means',
            'Committee on Youth and Sports Development',
            'Committee on Agriculture and Aquatic Resources',
            'Committee on Barangay Affairs',
            'Committee on Disaster Risk Reduction',
            'Committee on Economic Enterprises',
            'Committee on Education',
            'Committee on Games and Amusement',
            'Committee on General Services and Government Property Management',
            'Committee on Good Governance and Oversight',
            'Committee on Health and Wellness',
            'Committee on Information and Public Affairs',
            'Committee on Land Use, Zoning, and Urban Planning',
            'Committee on Local and International Relations',
            'Committee on Peace and Order and Public Safety',
            'Committee on Public Works and Other Utilities',
            'Committee on Rules, Ethics, Laws, Ordinances and Legal Affairs',
            'Committee on Smart and Sustainable Community',
            'Committee on Social Services',
            'Committee on Tourism, Culture, Arts, and Historical Preservation',
            'Committee on Trade, Commerce, and Industry',
            'Committee on Transportation and Traffic Management',
            'Committee on Urban and Rural Livelihood Development',
            'Committee on Women, Family and Gender and Development',
            'Committee on Workforce Development'
        ];

        foreach ($committees as $committeeName) {
            CommitteeRegistry::firstOrCreate(['name' => $committeeName]);
        }

        // 2. Statuses
        $statuses = [
            ['name' => 'New'],
            ['name' => 'Amendment'],
            ['name' => 'Repeal'],
            ['name' => 'Supersede'],
            ['name' => 'Suspended'],
            ['name' => 'Amended'],
        ];

        DB::table('statuses')->insert($statuses);   

        // 3. Classifications
        $classifications = [
            ['name' => 'Administrative'],
            ['name' => 'Executive'],
        ];
        
        DB::table('classifications')->insert($classifications);

        // 4. Users (Department IDs kept null or specific if you re-add departments later)
        $users = [
            [
                'name' => 'System Admin', 
                'email' => 'admin@gmail.com', 
                'role' => 'system_admin',
                'department_id' => null 
            ],
            [
                'name' => 'Office of the City Administrator', 
                'email' => 'adm@gmail.com', 
                'role' => 'supervisor', 
                'department_id' => null
            ],
            [
                'name' => 'Office of the Sangguniang Panlungsod', 
                'email' => 'osp@gmail.com', 
                'role' => 'supervisor', 
                'department_id' => null
            ],
            [
                'name' => 'Focal Person (Tourism)', 
                'email' => 'focal@gmail.com', 
                'role' => 'focal_person', 
                'department_id' => null 
            ],
            [
                'name' => 'Monitoring Committee', 
                'email' => 'rrt@gmail.com', 
                'role' => 'monitoring_committee',
                'department_id' => null
            ],
            [
                'name' => 'Kent Aldrin Tan', 
                'email' => 'guest@gmail.com', 
                'role' => 'read_only',
                'department_id' => null
            ],
        ];

        foreach ($users as $data) {
            User::factory()->create($data);
        }

        // 5. Ordinance Codes

        $ordinanceCodes = [
            ['name' => 'The Revenue Code', 'description' => 'Ordinances related to taxation and revenue generation.'],
            ['name' => 'The Zoning Code', 'description' => 'Ordinances governing land use, zoning, and urban planning.'],
            ['name' => 'The Environmental Code', 'description' => 'Ordinances focused on environmental protection and sustainability.'],
            ['name' => 'The Public Safety Code', 'description' => 'Ordinances related to peace and order, public safety, and disaster risk reduction.'],
            ['name' => 'The Health Code', 'description' => 'Ordinances concerning public health, wellness, and sanitation.'],
            ['name' => 'The Education Code', 'description' => 'Ordinances pertaining to education policies and programs.'],
            ['name' => 'The Social Services Code', 'description' => 'Ordinances addressing social welfare, family, and gender development.'],
            ['name' => 'The Tourism Code', 'description' => 'Ordinances promoting tourism, culture, arts, and historical preservation.'],
            ['name' => 'The Transportation Code', 'description' => 'Ordinances regulating transportation and traffic management.'],
            ['name' => 'The Trade and Commerce Code', 'description' => 'Ordinances related to trade, commerce, and industry.'],
        ];

        DB::table('ordinance_codes')->insert($ordinanceCodes);
    }
}