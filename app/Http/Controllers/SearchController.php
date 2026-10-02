<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Search\SearchGroup;
use App\Domain\Search\SearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Global search for the command palette (§45, §120).
 *
 * Plain JSON rather than an Inertia response: the palette is an overlay on the
 * current page, and a visit would replace what the user is searching from.
 */
final class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:120'],
        ]);

        $user = $request->user();

        if ($user === null) {
            return response()->json(['groups' => []]);
        }

        $groups = $search->search($validated['q'], $user);

        return response()->json([
            'groups' => $groups->map(fn (SearchGroup $group): array => $group->toArray())->all(),
            // Echoed back so the client can discard a response that arrived
            // after the user had already typed something else.
            'term' => $validated['q'],
        ]);
    }
}
