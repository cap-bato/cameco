<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = $this->faker->randomElement(['male', 'female']);
        $filipinoFirstNamesMale = [
            'Jose', 'Juan', 'Antonio', 'Ramon', 'Carlos', 'Andres', 'Emilio', 'Manuel', 'Roberto', 'Fernando',
            'Miguel', 'Rafael', 'Ricardo', 'Eduardo', 'Alfredo', 'Francisco', 'Isagani', 'Benigno', 'Gregorio', 'Vicente',
            'Pedro', 'Arturo', 'Enrique', 'Jaime', 'Leandro', 'Teodoro', 'Ernesto', 'Julio', 'Pablo', 'Tomas'
        ];
        $filipinoFirstNamesFemale = [
            'Maria', 'Cristina', 'Rosario', 'Luz', 'Ligaya', 'Corazon', 'Imelda', 'Gloria', 'Carmen', 'Teresa',
            'Elena', 'Victoria', 'Josefina', 'Dolores', 'Aurora', 'Amelia', 'Leticia', 'Patricia', 'Andrea', 'Angela',
            'Isabel', 'Cecilia', 'Estrella', 'Remedios', 'Virginia', 'Evelyn', 'Norma', 'Lourdes', 'Consuelo', 'Milagros'
        ];
        $filipinoLastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Ocampo', 'Garcia', 'Aquino', 'Torres', 'Mendoza', 'Flores',
            'Rivera', 'Villanueva', 'Gonzales', 'Ramos', 'Lopez', 'Dela Cruz', 'Pascual', 'Castro', 'Morales', 'Navarro',
            'Domingo', 'Salazar', 'Fernandez', 'Marquez', 'Padilla', 'Aguilar', 'Silva', 'Santiago', 'Soriano', 'Lim'
        ];
        $firstName = $gender === 'male'
            ? $this->faker->randomElement($filipinoFirstNamesMale)
            : $this->faker->randomElement($filipinoFirstNamesFemale);
        $middleName = $this->faker->randomElement($filipinoLastNames);
        $lastName = $this->faker->randomElement($filipinoLastNames);
        
        // Philippine-specific address components
        $cities = [
            'Manila', 'Quezon City', 'Makati City', 'Pasig City', 'Taguig City',
            'Mandaluyong City', 'Pasay City', 'Caloocan City', 'Las Piñas City',
            'Parañaque City', 'Muntinlupa City', 'Valenzuela City', 'Marikina City',
            'Navotas City', 'San Juan City', 'Malabon City'
        ];
        
        $streets = [
            'EDSA', 'Ortigas Avenue', 'Ayala Avenue', 'Taft Avenue', 'Roxas Boulevard',
            'España Boulevard', 'Commonwealth Avenue', 'Aurora Boulevard', 'Quezon Avenue',
            'Shaw Boulevard', 'Marcos Highway', 'C5 Road', 'Katipunan Avenue'
        ];
        
        $barangays = [
            'Barangay 1', 'Barangay San Antonio', 'Barangay Santa Cruz', 'Poblacion',
            'San Miguel', 'San Jose', 'Bagong Silang', 'Bel-Air', 'Forbes Park'
        ];

        return [
            'first_name' => $firstName,
            'middle_name' => $middleName,
            'last_name' => $lastName,
            'suffix' => $this->faker->optional(0.1)->randomElement(['Jr.', 'Sr.', 'II', 'III']),
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-22 years')->format('Y-m-d'),
            'gender' => $gender,
            'civil_status' => $this->faker->randomElement(['single', 'married', 'divorced', 'widowed', 'separated']),
            'phone' => $this->faker->optional(0.6)->numerify('(02) 8###-####'),
            'mobile' => '+63 ' . $this->faker->numerify('9## ### ####'),
            'current_address' => $this->faker->buildingNumber() . ' ' . 
                               $this->faker->randomElement($streets) . ', ' . 
                               $this->faker->randomElement($barangays) . ', ' . 
                               $this->faker->randomElement($cities),
            'permanent_address' => function (array $attributes) use ($streets, $barangays, $cities) {
                if ($this->faker->boolean(70)) {
                    return $attributes['current_address'];
                }
                return $this->faker->buildingNumber() . ' ' . 
                       $this->faker->randomElement($streets) . ', ' . 
                       $this->faker->randomElement($barangays) . ', ' . 
                       $this->faker->randomElement($cities);
            },
            'emergency_contact_name' => $this->faker->randomElement(array_merge($filipinoFirstNamesMale, $filipinoFirstNamesFemale)) . ' ' . $this->faker->randomElement($filipinoLastNames),
            'emergency_contact_relationship' => $this->faker->randomElement([
                'Spouse', 'Father', 'Mother', 'Sibling', 'Child', 'Partner', 'Friend'
            ]),
            'emergency_contact_phone' => '+63 ' . $this->faker->numerify('9## ### ####'),
            'emergency_contact_address' => $this->faker->optional(0.8)->passthrough(
                $this->faker->buildingNumber() . ' ' . 
                $this->faker->randomElement($streets) . ', ' . 
                $this->faker->randomElement($cities)
            ),
            'sss_number' => $this->faker->numerify('##-#######-#'),
            'tin_number' => $this->faker->numerify('###-###-###-000'),
            'philhealth_number' => $this->faker->numerify('##-###########-#'),
            'pagibig_number' => $this->faker->numerify('####-####-####'),
        ];
    }
}
