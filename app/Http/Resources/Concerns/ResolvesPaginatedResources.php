<?php

namespace App\Http\Resources\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

trait ResolvesPaginatedResources
{
    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @param  class-string<JsonResource>  $resourceClass
     * @return array{
     *     data: array<int, mixed>,
     *     links: array{
     *         first: string|null,
     *         last: string|null,
     *         prev: string|null,
     *         next: string|null
     *     },
     *     meta: array<string, mixed>
     * }
     */
    protected function paginatedResource(
        Request $request,
        LengthAwarePaginator $paginator,
        string $resourceClass,
    ): array {
        return $resourceClass::collection($paginator)
            ->response($request)
            ->getData(true);
    }
}
