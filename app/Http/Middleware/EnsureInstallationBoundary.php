<?php

namespace App\Http\Middleware;

use App\Domain\Demo\DemoBoundary;
use Closure;
use Illuminate\Http\Request;

class EnsureInstallationBoundary
{
    public function handle(Request $request, Closure $next)
    {
        DemoBoundary::database();
        $response = $next($request);
        if (DemoBoundary::enabled()) {
            $response->headers->set('X-HRIS-Environment', 'DEMO');
            $response->headers->set('X-HRIS-Scenario', config('hris.demo_scenario_id'));
            $response->headers->set('Cache-Control', 'no-store, private');
            if (str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
                $response->setContent(str_replace('<body', '<body data-hris-demo="true"', $response->getContent()));
                $response->setContent(preg_replace('/(<body[^>]*>)/i', '$1<div role="status" style="padding:12px;background:#fcdf8d;color:#242018;text-align:center;font:600 14px system-ui">DEMO · DATA SINTETIS · Skenario terisolasi</div>', $response->getContent(), 1));
            }
        }

        return $response;
    }
}
