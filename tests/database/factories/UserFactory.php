<?php

namespace TCG\Voyager\Tests\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
 
class UserFactory extends Factory
{
    protected $model = \TCG\Voyager\Models\User::class;

    public function definition()
    {
        static $password;

        return [
            'name'           => $this->faker->name(),
            'email'          => $this->faker->unique()->safeEmail(),
            'password'       => $password ?: $password = bcrypt('secret'),
            'remember_token' => Str::random(10),
            // Voyager users always belong to a role; the column is NOT NULL in the
            // test schema, so give the factory a real one instead of relying on a default.
            'role_id'        => RoleFactory::new(),
        ];
    }
}
