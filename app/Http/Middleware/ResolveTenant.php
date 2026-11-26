<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::clear();

        $slug = $this->resolveSlug($request);

        if ($slug === null) {
            return $next($request);
        }

        $tenant = Tenant::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        TenantContext::set($tenant);
        $request->attributes->set('tenant', $tenant);
        app()->instance(Tenant::class, $tenant);

        return $next($request);
    }

    protected function resolveSlug(Request $request): ?string
    {
        $centralDomains = config('tenancy.central_domains', []);

        $headerSlug = $request->header('X-Tenant-Slug');
        if (is_string($headerSlug) && $headerSlug !== '') {
            return strtolower($headerSlug);
        }

        foreach ([$request->getHost(), $this->hostFromOrigin($request)] as $host) {
            if ($host === null) {
                continue;
            }

            $host = strtolower($host);

            if (in_array($host, $centralDomains, true)) {
                continue;
            }

            $slug = $this->extractSlug($host, $centralDomains);

            if ($slug !== null) {
                return $slug;
            }
        }

        return null;
    }

    protected function hostFromOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin') ?? $request->headers->get('Referer');

        if (! $origin) {
            return null;
        }

        $host = parse_url($origin, PHP_URL_HOST);

        return is_string($host) ? $host : null;
    }

    /**
     * @param  list<string>  $centralDomains
     */
    protected function extractSlug(string $host, array $centralDomains): ?string
    {
        foreach ($centralDomains as $domain) {
            $suffix = '.'.$domain;

            if (str_ends_with($host, $suffix)) {
                $subdomain = substr($host, 0, -strlen($suffix));

                if ($subdomain === '' || str_contains($subdomain, '.')) {
                    return null;
                }

                return $subdomain;
            }
        }

        $baseDomain = config('tenancy.tenant_base_domain');
        $localBase = config('tenancy.local_tenant_base_domain');

        foreach ([$baseDomain, $localBase] as $domain) {
            if (! is_string($domain) || $domain === '') {
                continue;
            }

            $suffix = '.'.$domain;

            if (str_ends_with($host, $suffix)) {
                $subdomain = substr($host, 0, -strlen($suffix));

                if ($subdomain === '' || str_contains($subdomain, '.')) {
                    return null;
                }

                return $subdomain;
            }
        }

        return null;
    }
}
