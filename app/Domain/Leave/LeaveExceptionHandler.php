<?php

namespace App\Domain\Leave;

use App\Domain\Identity\Access;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

class LeaveExceptionHandler extends LeaveHandler
{
    public function approverRoles(): array
    {
        return ['hr_admin'];
    }

    public function prepare(User $actor, array $input): array
    {
        abort_unless(Access::companyPermission($actor, 'employees.manage'), 403);
        $exception = Validator::make($input, ['employee_id' => ['required', 'integer', 'exists:employees,id'], 'exception_reason' => ['required', 'string', 'max:2000']])->validate();
        $prepared = $this->prepareEmployee((int) $exception['employee_id'], $input, true);
        $prepared['payload'] += $exception;
        $prepared['summary']['notice_exception'] = true;

        return $prepared;
    }
}
