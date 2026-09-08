<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\Position;
use Illuminate\Database\Seeder;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Employee::count() > 0) {
            return;
        }

        $position = fn (string $code): ?int => Position::where('code', $code)->value('id');
        $department = fn (?string $code): ?int => $code ? \App\Models\Department::where('code', $code)->value('id') : null;

        $employees = [
            // Direction
            [
                'first_name' => 'Claire',
                'last_name' => 'Dubois',
                'email' => 'claire.dubois@company.com',
                'position' => 'DIR-RH',
                'department' => 'RH',
                'base_salary' => 85000,
                'hierarchy_level' => 4,
                'hiring_date' => '2019-03-01',
                'manager' => 0,
            ],
            [
                'first_name' => 'Marc',
                'last_name' => 'Lefevre',
                'email' => 'marc.lefevre@company.com',
                'position' => 'DIR-TECH',
                'department' => 'IT',
                'base_salary' => 95000,
                'hierarchy_level' => 4,
                'hiring_date' => '2018-06-15',
                'manager' => 0,
            ],
            [
                'first_name' => 'Sophie',
                'last_name' => 'Moreau',
                'email' => 'sophie.moreau@company.com',
                'position' => 'DIR-COM',
                'department' => 'VT',
                'base_salary' => 90000,
                'hierarchy_level' => 4,
                'hiring_date' => '2020-01-10',
                'manager' => 0,
            ],
            [
                'first_name' => 'Julien',
                'last_name' => 'Bernard',
                'email' => 'julien.bernard@company.com',
                'position' => 'DIR-FIN',
                'department' => null,
                'base_salary' => 92000,
                'hierarchy_level' => 4,
                'hiring_date' => '2017-09-01',
                'manager' => 0,
            ],
            // RH
            [
                'first_name' => 'Amelie',
                'last_name' => 'Rousseau',
                'email' => 'amelie.rousseau@company.com',
                'position' => 'RES-REC',
                'department' => 'RH',
                'base_salary' => 52000,
                'hierarchy_level' => 3,
                'hiring_date' => '2021-04-12',
                'manager' => 1,
            ],
            [
                'first_name' => 'Thomas',
                'last_name' => 'Garcia',
                'email' => 'thomas.garcia@company.com',
                'position' => 'CHG-RH',
                'department' => 'RH',
                'base_salary' => 38000,
                'hierarchy_level' => 2,
                'hiring_date' => '2022-11-01',
                'manager' => 1,
            ],
            // IT
            [
                'first_name' => 'Lucas',
                'last_name' => 'Petit',
                'email' => 'lucas.petit@company.com',
                'position' => 'LEAD-DEV',
                'department' => 'IT',
                'base_salary' => 72000,
                'hierarchy_level' => 3,
                'hiring_date' => '2020-08-24',
                'manager' => 2,
            ],
            [
                'first_name' => 'Emma',
                'last_name' => 'Dupont',
                'email' => 'emma.dupont@company.com',
                'position' => 'DEV-SR',
                'department' => 'IT',
                'base_salary' => 62000,
                'hierarchy_level' => 2,
                'hiring_date' => '2021-02-15',
                'manager' => 7,
            ],
            [
                'first_name' => 'Hugo',
                'last_name' => 'Lambert',
                'email' => 'hugo.lambert@company.com',
                'position' => 'DEV-FS',
                'department' => 'IT',
                'base_salary' => 50000,
                'hierarchy_level' => 2,
                'hiring_date' => '2023-05-08',
                'manager' => 7,
            ],
            [
                'first_name' => 'Lina',
                'last_name' => 'Fontaine',
                'email' => 'lina.fontaine@company.com',
                'position' => 'DEV-JR',
                'department' => 'IT',
                'base_salary' => 34000,
                'hierarchy_level' => 1,
                'hiring_date' => '2024-09-02',
                'contract_type' => 'cdd',
                'manager' => 7,
            ],
            [
                'first_name' => 'Nathan',
                'last_name' => 'Girard',
                'email' => 'nathan.girard@company.com',
                'position' => 'ENG-DEV',
                'department' => 'IT',
                'base_salary' => 55000,
                'hierarchy_level' => 2,
                'hiring_date' => '2022-03-20',
                'manager' => 2,
            ],
            // Ventes & Marketing
            [
                'first_name' => 'Chloe',
                'last_name' => 'Bonnet',
                'email' => 'chloe.bonnet@company.com',
                'position' => 'COM-SR',
                'department' => 'VT',
                'base_salary' => 48000,
                'hierarchy_level' => 2,
                'hiring_date' => '2021-10-05',
                'manager' => 3,
            ],
            [
                'first_name' => 'Maxime',
                'last_name' => 'Chevalier',
                'email' => 'maxime.chevalier@company.com',
                'position' => 'COM',
                'department' => 'VT',
                'base_salary' => 36000,
                'hierarchy_level' => 1,
                'hiring_date' => '2023-01-16',
                'manager' => 3,
            ],
            [
                'first_name' => 'Manon',
                'last_name' => 'Masson',
                'email' => 'manon.masson@company.com',
                'position' => 'CM',
                'department' => 'VT',
                'base_salary' => 35000,
                'hierarchy_level' => 1,
                'hiring_date' => '2024-02-26',
                'contract_type' => 'cdd',
                'manager' => 3,
            ],
            // Finance
            [
                'first_name' => 'Arthur',
                'last_name' => 'Renard',
                'email' => 'arthur.renard@company.com',
                'position' => 'CPT-SR',
                'department' => null,
                'base_salary' => 54000,
                'hierarchy_level' => 2,
                'hiring_date' => '2020-07-13',
                'manager' => 4,
            ],
            [
                'first_name' => 'Jade',
                'last_name' => 'Blanc',
                'email' => 'jade.blanc@company.com',
                'position' => 'CPT',
                'department' => null,
                'base_salary' => 40000,
                'hierarchy_level' => 1,
                'hiring_date' => '2023-08-21',
                'manager' => 4,
            ],
        ];

        $created = [];

        foreach ($employees as $i => $definition) {
            $managerId = null;

            if ($definition['manager'] > 0 && isset($created[$definition['manager']])) {
                $managerId = $created[$definition['manager']]->id;
            }

            $created[$i] = Employee::create([
                'registration_number' => 'EMP-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT),
                'first_name' => $definition['first_name'],
                'last_name' => $definition['last_name'],
                'email' => $definition['email'],
                'phone' => '+33 6 ' . str_pad((string) (1000000 + $i * 137), 8, '0', STR_PAD_LEFT),
                'gender' => in_array($definition['first_name'], ['Claire', 'Amelie', 'Emma', 'Lina', 'Chloe', 'Manon', 'Jade'])
                    ? 'female'
                    : 'male',
                'nationality' => 'French',
                'marital_status' => 'single',
                'children_count' => 0,
                'hiring_date' => $definition['hiring_date'],
                'contract_type' => $definition['contract_type'] ?? 'cdi',
                'status' => 'active',
                'department_id' => $department($definition['department']),
                'position_id' => $position($definition['position']),
                'hierarchy_level' => $definition['hierarchy_level'],
                'base_salary' => $definition['base_salary'],
                'manager_id' => $managerId,
            ]);
        }
    }
}
