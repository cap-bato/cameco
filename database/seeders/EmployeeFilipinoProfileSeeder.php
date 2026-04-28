<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class EmployeeFilipinoProfileSeeder extends Seeder
{
    /**
     * Assign Filipino male names and mock photos to all seeded employees.
     * Photos are read from employee-photos-mock/ and stored in the public disk.
     */
    public function run(): void
    {
        $filipinoNames = [
            ['first_name' => 'Felipe',      'middle_name' => 'Santos',    'last_name' => 'Dela Cruz'],
            ['first_name' => 'Pedro',     'middle_name' => 'Reyes',     'last_name' => 'Bautista'],
            ['first_name' => 'Jose',      'middle_name' => 'Garcia',    'last_name' => 'Aquino'],
            ['first_name' => 'Miguel',    'middle_name' => 'Lopez',     'last_name' => 'Santos'],
            ['first_name' => 'Antonio',   'middle_name' => 'Cruz',      'last_name' => 'Villanueva'],
            ['first_name' => 'Rafael',    'middle_name' => 'Bautista',  'last_name' => 'Mendoza'],
            ['first_name' => 'Gabriel',   'middle_name' => 'Torres',    'last_name' => 'Ramos'],
            ['first_name' => 'Carlos',    'middle_name' => 'Ramos',     'last_name' => 'Flores'],
            ['first_name' => 'Luis',      'middle_name' => 'Mendoza',   'last_name' => 'Torres'],
            ['first_name' => 'Fernando',  'middle_name' => 'Flores',    'last_name' => 'Rivera'],
            ['first_name' => 'Ricardo',   'middle_name' => 'Rivera',    'last_name' => 'Gonzales'],
            ['first_name' => 'Marco',     'middle_name' => 'Gonzales',  'last_name' => 'Fernandez'],
            ['first_name' => 'Paolo',     'middle_name' => 'Fernandez', 'last_name' => 'Castillo'],
            ['first_name' => 'Eduardo',   'middle_name' => 'Castillo',  'last_name' => 'Morales'],
            ['first_name' => 'Santiago',  'middle_name' => 'Morales',   'last_name' => 'Navarro'],
            ['first_name' => 'Francisco', 'middle_name' => 'Navarro',   'last_name' => 'De Leon'],
            ['first_name' => 'Andres',    'middle_name' => 'Pascual',   'last_name' => 'Santiago'],
            ['first_name' => 'Ramon',     'middle_name' => 'Espino',    'last_name' => 'Hernandez'],
            ['first_name' => 'Manuel',    'middle_name' => 'Aguilar',   'last_name' => 'Ocampo'],
            ['first_name' => 'Ernesto',   'middle_name' => 'De Leon',   'last_name' => 'Tolentino'],
            ['first_name' => 'Roberto',   'middle_name' => 'Martinez',  'last_name' => 'Rosario'],
            ['first_name' => 'Arturo',    'middle_name' => 'Hernandez', 'last_name' => 'Jimenez'],
            ['first_name' => 'Vicente',   'middle_name' => 'Santiago',  'last_name' => 'Sarmiento'],
            ['first_name' => 'Emilio',    'middle_name' => 'Valdez',    'last_name' => 'Imperial'],
            ['first_name' => 'Julio',     'middle_name' => 'Peralta',   'last_name' => 'Magno'],
            ['first_name' => 'Leonardo',  'middle_name' => 'Salazar',   'last_name' => 'Corpuz'],
            ['first_name' => 'Angelo',    'middle_name' => 'Diaz',      'last_name' => 'Panganiban'],
            ['first_name' => 'Dominic',   'middle_name' => 'Velasco',   'last_name' => 'Lacson'],
            ['first_name' => 'Renato',    'middle_name' => 'Miranda',   'last_name' => 'Cruz'],
            ['first_name' => 'Benjamin',  'middle_name' => 'Santos',    'last_name' => 'Padilla'],
            ['first_name' => 'David',     'middle_name' => 'Reyes',     'last_name' => 'Roque'],
            ['first_name' => 'Daniel',    'middle_name' => 'Garcia',    'last_name' => 'Lozano'],
            ['first_name' => 'Christian', 'middle_name' => 'Lopez',     'last_name' => 'Alvarez'],
            ['first_name' => 'Jayson',    'middle_name' => 'Cruz',      'last_name' => 'Cabrera'],
            ['first_name' => 'Mark',      'middle_name' => 'Bautista',  'last_name' => 'Dominguez'],
            ['first_name' => 'James',     'middle_name' => 'Torres',    'last_name' => 'Soriano'],
            ['first_name' => 'John',      'middle_name' => 'Ramos',     'last_name' => 'Manalo'],
            ['first_name' => 'Michael',   'middle_name' => 'Mendoza',   'last_name' => 'Bernardo'],
            ['first_name' => 'Jerome',    'middle_name' => 'Flores',    'last_name' => 'Pascual'],
            ['first_name' => 'Ronald',    'middle_name' => 'Rivera',    'last_name' => 'Espino'],
            ['first_name' => 'Dennis',    'middle_name' => 'Gonzales',  'last_name' => 'Aguilar'],
            ['first_name' => 'Kenneth',   'middle_name' => 'Fernandez', 'last_name' => 'Gutierrez'],
            ['first_name' => 'Jeffrey',   'middle_name' => 'Castillo',  'last_name' => 'Salazar'],
            ['first_name' => 'Patrick',   'middle_name' => 'Morales',   'last_name' => 'Velasco'],
            ['first_name' => 'Vincent',   'middle_name' => 'Navarro',   'last_name' => 'Diaz'],
            ['first_name' => 'Gerald',    'middle_name' => 'Pascual',   'last_name' => 'Enriquez'],
            ['first_name' => 'Harold',    'middle_name' => 'Espino',    'last_name' => 'Mercado'],
            ['first_name' => 'Bryan',     'middle_name' => 'Aguilar',   'last_name' => 'Garcia'],
            ['first_name' => 'Cedric',    'middle_name' => 'De Leon',   'last_name' => 'Reyes'],
            ['first_name' => 'Ryan',      'middle_name' => 'Martinez',  'last_name' => 'Santos'],
            ['first_name' => 'Darwin',    'middle_name' => 'Hernandez', 'last_name' => 'Cruz'],
            ['first_name' => 'Marvin',    'middle_name' => 'Santiago',  'last_name' => 'Lopez'],
            ['first_name' => 'Ariel',     'middle_name' => 'Valdez',    'last_name' => 'Bautista'],
            ['first_name' => 'Ruel',      'middle_name' => 'Peralta',   'last_name' => 'Torres'],
            ['first_name' => 'Joel',      'middle_name' => 'Salazar',   'last_name' => 'Ramos'],
            ['first_name' => 'Allan',     'middle_name' => 'Diaz',      'last_name' => 'Mendoza'],
            ['first_name' => 'Nelson',    'middle_name' => 'Velasco',   'last_name' => 'Flores'],
            ['first_name' => 'Roderick',  'middle_name' => 'Miranda',   'last_name' => 'Rivera'],
            ['first_name' => 'Gilbert',   'middle_name' => 'Santos',    'last_name' => 'Gonzales'],
            ['first_name' => 'Rex',       'middle_name' => 'Reyes',     'last_name' => 'Fernandez'],
            ['first_name' => 'Alvin',     'middle_name' => 'Garcia',    'last_name' => 'Castillo'],
            ['first_name' => 'Arnold',    'middle_name' => 'Lopez',     'last_name' => 'Morales'],
            ['first_name' => 'Leo',       'middle_name' => 'Cruz',      'last_name' => 'Navarro'],
            ['first_name' => 'Felix',     'middle_name' => 'Bautista',  'last_name' => 'De Leon'],
            ['first_name' => 'Andy',      'middle_name' => 'Torres',    'last_name' => 'Pascual'],
            ['first_name' => 'Edgar',     'middle_name' => 'Ramos',     'last_name' => 'Espino'],
            ['first_name' => 'Ruben',     'middle_name' => 'Mendoza',   'last_name' => 'Aguilar'],
            ['first_name' => 'Raymond',   'middle_name' => 'Flores',    'last_name' => 'Martinez'],
            ['first_name' => 'Noel',      'middle_name' => 'Rivera',    'last_name' => 'Hernandez'],
            ['first_name' => 'Henry',     'middle_name' => 'Gonzales',  'last_name' => 'Santiago'],
            ['first_name' => 'Sergio',    'middle_name' => 'Fernandez', 'last_name' => 'Valdez'],
            ['first_name' => 'Alfred',    'middle_name' => 'Castillo',  'last_name' => 'Peralta'],
            ['first_name' => 'Enrique',   'middle_name' => 'Morales',   'last_name' => 'Salazar'],
        ];

        $photoFiles = [
            'male.jpg',  'male2.jpg',  'male3.jpg',  'male4.jpg',  'male5.jpg',
            'male6.jpg', 'male7.jpg',  'male8.jpg',  'male9.jpg',  'male10.jpg',
            'male11.jpg','male12.jpg', 'male13.jpg', 'male14.jpg', 'male15.jpg',
            'male16.jpg','male17.jpg', 'male18.jpg', 'male19.jpg', 'male20.jpg',
        ];

        $sourceDir = base_path('employee-photos-mock');
        $employees = Employee::with('profile')->orderBy('id')->get();

        $this->command->info("EmployeeFilipinoProfileSeeder: updating {$employees->count()} employees...");

        $i = 0;
        foreach ($employees as $employee) {
            $profile = $employee->profile;

            if (!$profile) {
                $this->command->warn("  SKIP ID {$employee->id} ({$employee->employee_number}) — no profile");
                $i++;
                continue;
            }

            if ($i >= count($filipinoNames)) {
                $this->command->warn("  SKIP ID {$employee->id} — ran out of names");
                continue;
            }

            $name      = $filipinoNames[$i];
            $photoFile = $photoFiles[$i % count($photoFiles)];
            $sourcePath = $sourceDir . '/' . $photoFile;
            $destPath   = "employees/{$employee->employee_number}/profile.jpg";

            if (file_exists($sourcePath)) {
                Storage::disk('public')->put($destPath, file_get_contents($sourcePath));
            } else {
                $this->command->warn("  Photo missing: {$sourcePath} — skipping photo for {$employee->employee_number}");
                $destPath = null;
            }

            $updateData = [
                'first_name'  => $name['first_name'],
                'middle_name' => $name['middle_name'],
                'last_name'   => $name['last_name'],
                'gender'      => 'male',
            ];

            if ($destPath) {
                $updateData['profile_picture_path'] = $destPath;
            }

            $profile->update($updateData);

            $i++;
        }

        $this->command->info("  Done — updated {$i} employee profiles.");
    }
}
