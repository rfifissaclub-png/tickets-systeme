<?php

namespace Database\Factories;

use App\Models\ClientContact;
use App\Models\Tenant;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ClientContact> */
class ClientContactFactory extends Factory
{
    protected $model = ClientContact::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'client_id' => Client::factory(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'phone' => null,
            'role' => null,
            'meta' => null,
        ];
    }
}
