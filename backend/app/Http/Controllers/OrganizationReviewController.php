<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReviewIndexRequest;
use App\Http\Resources\ReviewResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class OrganizationReviewController extends Controller
{
    /**
     * Отзывы организации из собственной БД, по 50 на страницу.
     */
    public function index(ReviewIndexRequest $request): JsonResponse
    {
        $organization = $request->user()->organizations()->first();

        if ($organization === null) {
            return response()->json([
                'message' => 'Сначала добавьте ссылку на карточку организации.',
            ], Response::HTTP_NOT_FOUND);
        }

        $reviews = $organization->reviews()
            ->orderByDesc('review_date')
            ->orderByDesc('id')
            ->paginate(
                perPage: ReviewIndexRequest::PER_PAGE,
                page: $request->page(),
            );

        return response()->json([
            'data' => ReviewResource::collection($reviews->items()),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'per_page' => $reviews->perPage(),
                'total' => $reviews->total(),
                'last_page' => $reviews->lastPage(),
            ],
        ]);
    }
}
