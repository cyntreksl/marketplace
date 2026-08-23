<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Concerns\SellerProfileValidationRules;
use App\Models\User;
use App\Services\SellerOnboardingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules, SellerProfileValidationRules;

    public function __construct(private SellerOnboardingService $sellerOnboarding) {}

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $rules = [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
            'registration_type' => ['nullable', Rule::in(['vendor'])],
        ];

        if (($input['registration_type'] ?? null) === 'vendor') {
            $rules = [...$rules, ...$this->sellerProfileRules()];
        }

        $validated = Validator::make($input, $rules)->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            if (($validated['registration_type'] ?? null) === 'vendor') {
                $this->sellerOnboarding->store($user, $validated);
            }

            return $user;
        });
    }
}
