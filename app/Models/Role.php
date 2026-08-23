<?php

namespace App\Models;

use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'label'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    public const Buyer = 'buyer';

    public const IndividualSeller = 'individual_seller';

    public const BusinessSeller = 'business_seller';

    public const Admin = 'admin';

    public const FinanceAdmin = 'finance_admin';

    public const SuperAdmin = 'super_admin';

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
