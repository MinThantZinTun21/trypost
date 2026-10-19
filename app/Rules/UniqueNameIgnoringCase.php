<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Fails when another row in the scope already has this name, ignoring case.
 * MySQL's unique index already ignores case and PostgreSQL's does not, so the
 * rule refuses what either engine would, and the index never raises a 500.
 */
class UniqueNameIgnoringCase implements ValidationRule
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $scope  the rows the name must be unique among
     */
    public function __construct(private Builder $scope, private ?string $ignoreId = null) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $value);

        $taken = $this->scope
            ->whereLike('name', $literal)
            ->when($this->ignoreId, fn (Builder $query, string $id) => $query->whereKeyNot($id))
            ->exists();

        if ($taken) {
            $fail('validation.unique')->translate();
        }
    }
}
