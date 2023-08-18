<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait AuthorizedRelationLoader
{
    private function filterLoadableRelations(array|string $requestedRelations, string $modelClass): array
    {
        if (is_string($requestedRelations)){
            $requestedRelations = explode(',', $requestedRelations);
        }

        $requested = collect($requestedRelations);

        return $requested->filter(function ($relation) use ($modelClass) {
            $loadableRelations = $modelClass::getloadableRelations();

            return isset($loadableRelations[$relation]) && Auth::user()->can($loadableRelations[$relation]);
        })->values()->toArray();
    }

}