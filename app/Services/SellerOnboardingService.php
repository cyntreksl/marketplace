<?php

namespace App\Services;

use App\Models\Role;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SellerOnboardingService
{
    public function __construct(private readonly SeoRedirectService $redirects) {}

    /** @param array<string, mixed> $data */
    public function store(User $user, array $data): SellerProfile
    {
        return DB::transaction(function () use ($user, $data): SellerProfile {
            $oldSlug = $user->sellerProfile()->value('slug');
            $profile = SellerProfile::query()->updateOrCreate(['user_id' => $user->id], [
                ...$data,
                'slug' => Str::slug($data['store_name']).'-'.$user->id,
                'status' => 'pending_review',
                'terms_accepted_at' => now(),
            ]);

            $isBusinessSeller = $data['seller_type'] === 'business';
            $role = Role::query()->firstOrCreate(
                ['name' => $isBusinessSeller ? Role::BusinessSeller : Role::IndividualSeller],
                ['label' => $isBusinessSeller ? 'Business Seller' : 'Individual Seller'],
            );
            $user->roles()->syncWithoutDetaching([$role->id]);
            $this->redirects->recordSlugChange('stores', is_string($oldSlug) ? $oldSlug : null, $profile->slug);

            return $profile;
        });
    }
}
