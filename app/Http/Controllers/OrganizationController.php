<?php

namespace App\Http\Controllers;

use App\Domain\CoreHR\OrganizationCatalog;
use App\Domain\CoreHR\OrganizationWriter;
use App\Domain\Identity\Access;
use App\Http\Requests\OrganizationRequest;
use App\Models\Department;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request, string $kind = 'branches')
    {
        abort_unless(Access::companyPermission($request->user(), 'organization.manage'), 403);
        $type = OrganizationCatalog::type($kind);

        return view('organization.index', ['kind' => $kind, 'type' => $type, 'records' => $type['model']::orderBy('code')->paginate(20)]);
    }

    public function form(Request $request, string $kind, ?string $record = null)
    {
        abort_unless(Access::companyPermission($request->user(), 'organization.manage'), 403);
        $type = OrganizationCatalog::type($kind);

        $model = $record ? $type['model']::findOrFail($record) : new ($type['model']);
        $existingParent = $kind === 'jobs' ? $model->department_id : $model->parent_id;

        return view('organization.form', ['kind' => $kind, 'type' => $type, 'record' => $model, 'departments' => Department::where(fn ($q) => $q->where('is_active', true)->orWhere('id', $existingParent))->orderBy('name')->get()]);
    }

    public function store(OrganizationRequest $request, string $kind, OrganizationWriter $writer)
    {
        $writer->save($request->user(), $kind, null, $request->validated());

        return redirect()->route('workspace.organization', $kind)->with('status', 'Master organisasi berhasil ditambahkan.');
    }

    public function update(OrganizationRequest $request, string $kind, string $record, OrganizationWriter $writer)
    {
        $writer->save($request->user(), $kind, (int) $record, $request->validated());

        return redirect()->route('workspace.organization', $kind)->with('status', 'Master organisasi berhasil diperbarui.');
    }
}
