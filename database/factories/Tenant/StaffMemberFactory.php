<?php

declare(strict_types=1);

namespace Database\Factories\Tenant;

use App\Models\Tenant\StaffMember;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<StaffMember>
 */
class StaffMemberFactory extends Factory
{
    protected $model = StaffMember::class;

    public function definition(): array
    {
        $username = fake()->unique()->userName();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'avatar_path' => null,
            'role' => 'Dentist',
            'clinic_access_level' => 'staff',
            'specialty' => null,
            'experience' => null,
            'status' => 'Active',
            'username' => $username,
            'sign_in_method' => 'pin',
            'pin_length' => 4,
            'login_pin' => Hash::make('1234'),
            'login_password' => null,
            'color' => null,
            'annual_leave_days' => null,
            'leave_start' => null,
            'leave_end' => null,
            'paid_by_percentage' => false,
        ];
    }

    public function passwordSignIn(string $plainPassword = 'secret-password'): static
    {
        return $this->state(fn (array $attributes) => [
            'sign_in_method' => 'password',
            'login_pin' => null,
            'login_password' => $plainPassword,
        ]);
    }
}
