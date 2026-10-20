<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $guarded = ['id'];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function periods(): HasMany
    {
        return $this->hasMany(EmploymentPeriod::class);
    }

    public function statusAt(string $date): string
    {
        $period = $this->periods()->where('starts_on', '<=', $date)->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>', $date))->first();
        if ($period) {
            if ($period->activation_state === 'pending') {
                return 'PREBOARDING';
            }

            return $period->offboarding ? 'OFFBOARDING' : 'ACTIVE';
        }

        return $this->periods()->where('starts_on', '>', $date)->exists() ? 'PREBOARDING' : 'ENDED';
    }
}
