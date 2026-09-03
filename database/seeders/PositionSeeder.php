<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Position;
use Illuminate\Database\Seeder;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get departments
        $rh = Department::where('code', 'RH')->first();
        $it = Department::where('code', 'IT')->first();
        $dev = Department::where('code', 'DEV')->first();
        $inf = Department::where('code', 'INF')->first();
        $ventes = Department::where('code', 'VT')->first();
        $marketing = Department::where('code', 'MKT')->first();
        $finance = Department::where('code', 'FIN')->first();

        // HR Positions
        $dirRh = Position::create([
            'title' => 'Directeur Ressources Humaines',
            'code' => 'DIR-RH',
            'description' => 'Direction du département RH',
            'level' => 'director',
            'department_id' => $rh->id,
            'min_salary' => 80000,
            'max_salary' => 120000,
        ]);

        Position::create([
            'title' => 'Responsable Recrutement',
            'code' => 'RES-REC',
            'description' => 'Gestion du recrutement et des candidatures',
            'level' => 'manager',
            'department_id' => $rh->id,
            'min_salary' => 35000,
            'max_salary' => 55000,
        ]);

        Position::create([
            'title' => 'Chargé RH',
            'code' => 'CHG-RH',
            'description' => 'Support RH généraliste',
            'level' => 'mid',
            'department_id' => $rh->id,
            'min_salary' => 28000,
            'max_salary' => 42000,
        ]);

        // IT Development Positions
        $dirDev = Position::create([
            'title' => 'Directeur Technique',
            'code' => 'DIR-TECH',
            'description' => 'Direction de la stratégie technique',
            'level' => 'director',
            'department_id' => $dev->id,
            'min_salary' => 90000,
            'max_salary' => 130000,
        ]);

        Position::create([
            'title' => 'Lead Developer',
            'code' => 'LEAD-DEV',
            'description' => 'Responsable technique et coaching d\'équipe',
            'level' => 'lead',
            'department_id' => $dev->id,
            'min_salary' => 55000,
            'max_salary' => 80000,
        ]);

        Position::create([
            'title' => 'Développeur Senior',
            'code' => 'DEV-SR',
            'description' => 'Développeur expérimenté',
            'level' => 'senior',
            'department_id' => $dev->id,
            'min_salary' => 45000,
            'max_salary' => 65000,
        ]);

        Position::create([
            'title' => 'Développeur Full Stack',
            'code' => 'DEV-FS',
            'description' => 'Développeur front-end et back-end',
            'level' => 'mid',
            'department_id' => $dev->id,
            'min_salary' => 35000,
            'max_salary' => 55000,
        ]);

        Position::create([
            'title' => 'Développeur Junior',
            'code' => 'DEV-JR',
            'description' => 'Développeur débutant',
            'level' => 'junior',
            'department_id' => $dev->id,
            'min_salary' => 25000,
            'max_salary' => 35000,
        ]);

        // IT Infrastructure Positions
        Position::create([
            'title' => 'Responsable Infrastructure',
            'code' => 'RES-INF',
            'description' => 'Gestion de l\'infrastructure IT',
            'level' => 'manager',
            'department_id' => $inf->id,
            'min_salary' => 40000,
            'max_salary' => 65000,
        ]);

        Position::create([
            'title' => 'Ingénieur DevOps',
            'code' => 'ENG-DEV',
            'description' => 'Gestion du déploiement et de l\'infrastructure',
            'level' => 'senior',
            'department_id' => $inf->id,
            'min_salary' => 45000,
            'max_salary' => 65000,
        ]);

        // Sales Positions
        Position::create([
            'title' => 'Directeur Commercial',
            'code' => 'DIR-COM',
            'description' => 'Direction commerciale',
            'level' => 'director',
            'department_id' => $ventes->id,
            'min_salary' => 70000,
            'max_salary' => 110000,
        ]);

        Position::create([
            'title' => 'Commercial Senior',
            'code' => 'COM-SR',
            'description' => 'Commercial confirmé avec portefeuille clients',
            'level' => 'senior',
            'department_id' => $ventes->id,
            'min_salary' => 40000,
            'max_salary' => 70000,
        ]);

        Position::create([
            'title' => 'Commercial',
            'code' => 'COM',
            'description' => 'Commercial',
            'level' => 'mid',
            'department_id' => $ventes->id,
            'min_salary' => 30000,
            'max_salary' => 50000,
        ]);

        // Marketing Positions
        Position::create([
            'title' => 'Responsable Marketing',
            'code' => 'RES-MKT',
            'description' => 'Gestion stratégique du marketing',
            'level' => 'manager',
            'department_id' => $marketing->id,
            'min_salary' => 38000,
            'max_salary' => 60000,
        ]);

        Position::create([
            'title' => 'Community Manager',
            'code' => 'CM',
            'description' => 'Gestion des réseaux sociaux et communauté',
            'level' => 'mid',
            'department_id' => $marketing->id,
            'min_salary' => 28000,
            'max_salary' => 42000,
        ]);

        // Finance Positions
        Position::create([
            'title' => 'Directeur Financier',
            'code' => 'DIR-FIN',
            'description' => 'Direction financière',
            'level' => 'director',
            'department_id' => $finance->id,
            'min_salary' => 75000,
            'max_salary' => 115000,
        ]);

        Position::create([
            'title' => 'Comptable Senior',
            'code' => 'CPT-SR',
            'description' => 'Comptable expérimenté',
            'level' => 'senior',
            'department_id' => $finance->id,
            'min_salary' => 35000,
            'max_salary' => 55000,
        ]);

        Position::create([
            'title' => 'Comptable',
            'code' => 'CPT',
            'description' => 'Comptable généraliste',
            'level' => 'mid',
            'department_id' => $finance->id,
            'min_salary' => 28000,
            'max_salary' => 42000,
        ]);
    }
}
