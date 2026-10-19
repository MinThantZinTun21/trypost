<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MobileToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MobileToken>
 */
class MobileTokenFactory extends Factory
{
    protected $model = MobileToken::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'token_hash' => MobileToken::hash(bin2hex(random_bytes(40))),
        ];
    }
}
