<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create root departments
        $directionGenerale = Department::create([
            'name' => 'Direction Générale',
            'code' => 'DG',
            'description' => 'Direction générale de l\'entreprise',
            'budget' => 500000,
            'is_active' => true,
        ]);

        $ressourcesHumaines = Department::create([
            'name' => 'Ressources Humaines',
            'code' => 'RH',
            'description' => 'Département des ressources humaines',
            'parent_id' => $directionGenerale->id,
            'budget' => 150000,
            'is_active' => true,
        ]);

        $informatique = Department::create([
            'name' => 'Informatique',
            'code' => 'IT',
            'description' => 'Département informatique et développement',
            'parent_id' => $directionGenerale->id,
            'budget' => 300000,
            'is_active' => true,
        ]);

        $ventes = Department::create([
            'name' => 'Ventes',
            'code' => 'VT',
            'description' => 'Département des ventes et commercial',
            'parent_id' => $directionGenerale->id,
            'budget' => 200000,
            'is_active' => true,
        ]);

        $marketing = Department::create([
            'name' => 'Marketing',
            'code' => 'MKT',
            'description' => 'Département marketing et communication',
            'parent_id' => $directionGenerale->id,
            'budget' => 120000,
            'is_active' => true,
        ]);

        $finance = Department::create([
            'name' => 'Finance',
            'code' => 'FIN',
            'description' => 'Département finance et comptabilité',
            'parent_id' => $directionGenerale->id,
            'budget' => 180000,
            'is_active' => true,
        ]);

        // Create sub-departments for IT
        $developpement = Department::create([
            'name' => 'Développement',
            'code' => 'DEV',
            'description' => 'Équipe de développement logiciel',
            'parent_id' => $informatique->id,
            'budget' => 180000,
            'is_active' => true,
        ]);

        $infrastructure = Department::create([
            'name' => 'Infrastructure',
            'code' => 'INF',
            'description' => 'Équipe infrastructure et DevOps',
            'parent_id' => $informatique->id,
            'budget' => 120000,
            'is_active' => true,
        ]);

        // Create test employees to act as managers (already exist from UserRoleSeeder or EmployeeFactory)
        // For now, we just create the departments structure
        // In a real scenario, employees would be assigned as managers later
    }
}
