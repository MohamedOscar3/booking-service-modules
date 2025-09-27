<?php

namespace Modules\Category\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Auth\Models\User;
use Modules\Category\Database\Factories\CategoryFactory;

/**
 * Category model
 *
 * @property int $id
 * @property string $name
 * @property int $user_id
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read User $lastUpdatedByUser
 */
class Category extends Model
{
    /** @use HasFactory<\Modules\Category\Database\Factories\CategoryFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'last_updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    protected function lastUpdatedBy(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => auth()->guard('sanctum')->id() ?? $value,
        );
    }

    /**
     * Get the user that owns the category.
     *
     * @return BelongsTo<User, Category>
     */
    public function lastUpdatedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    protected static function newFactory()
    {
        return CategoryFactory::new();
    }
}
