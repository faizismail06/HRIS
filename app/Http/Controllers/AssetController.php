<?php

namespace App\Http\Controllers;

use App\Domain\Assets\AssetService;
use App\Domain\Assets\AssetSettlements;
use App\Domain\Identity\Access;
use App\Http\Requests\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetController extends Controller
{
    public function resolve(OperationalRequest $r, int $id, AssetSettlements $s)
    {
        return response()->json($s->resolve($r->user(), $id, $r->version(), $r->key(), $r->all()));
    }

    public function store(OperationalRequest $r, AssetService $service)
    {
        return response()->json($service->create($r->user(), $r->key(), $r->all()), 201);
    }

    public function issue(OperationalRequest $r, int $id, AssetService $service)
    {
        return response()->json($service->issue($r->user(), $id, $r->version(), $r->key(), $r->all()), 201);
    }

    public function accept(OperationalRequest $r, int $id, AssetService $service)
    {
        return response()->json($service->accept($r->user(), $id, $r->version(), $r->key()));
    }

    public function returnAsset(OperationalRequest $r, int $id, AssetService $service)
    {
        return response()->json($service->returnAsset($r->user(), $id, $r->version(), $r->key(), $r->all()));
    }

    public function consumable(OperationalRequest $r, AssetService $service)
    {
        return response()->json($service->consumable($r->user(), $r->key(), $r->all()), 201);
    }

    public function index(Request $r)
    {
        abort_unless(Access::companyPermission($r->user(), 'assets.manage'), 403);

        return response()->json(DB::table('assets')->orderBy('id')->paginate(50));
    }

    public function mine(Request $r)
    {
        abort_unless($r->user()->employee_id && $r->user()->grants()->where('role', 'employee')->where('scope', 'self')->exists(), 403);

        return response()->json(['data' => DB::table('asset_custodies')->join('assets', 'assets.id', '=', 'asset_custodies.asset_id')->where('employee_id', $r->user()->employee_id)->orderByDesc('asset_custodies.id')->limit(100)->get(['asset_custodies.id', 'assets.code', 'assets.category', 'asset_custodies.issued_at', 'asset_custodies.accepted_at', 'asset_custodies.returned_at', 'asset_custodies.closure_kind', 'asset_custodies.version'])]);
    }
}
