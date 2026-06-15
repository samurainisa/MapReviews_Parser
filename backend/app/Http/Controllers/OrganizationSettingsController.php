<?php

namespace App\Http\Controllers;

use App\Actions\Organization\SaveOrganizationSourceAction;
use App\Actions\Organization\StartOrganizationParsingAction;
use App\Http\Requests\SaveOrganizationUrlRequest;
use App\Http\Resources\OrganizationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class OrganizationSettingsController extends Controller
{
    /**
     * Текущая организация пользователя (или null, если ещё не подключена).
     */
    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()->organizations()->first();

        return response()->json([
            'organization' => $organization
                ? new OrganizationResource($organization)
                : null,
        ]);
    }

    /**
     * Сохранить ссылку и запустить парсинг.
     */
    public function store(
        SaveOrganizationUrlRequest $request,
        SaveOrganizationSourceAction $action,
    ): JsonResponse {
        $organization = $action->execute(
            $request->user(),
            $request->string('source_url')->toString(),
        );

        return response()->json([
            'organization' => new OrganizationResource($organization),
        ], Response::HTTP_ACCEPTED);
    }

    /**
     * Повторный запуск парсинга без повторного ввода ссылки.
     */
    public function refresh(
        Request $request,
        StartOrganizationParsingAction $action,
    ): JsonResponse {
        $organization = $request->user()->organizations()->first();

        if ($organization === null) {
            return response()->json([
                'message' => 'Сначала добавьте ссылку на карточку организации.',
            ], Response::HTTP_NOT_FOUND);
        }

        $action->execute($organization);

        return response()->json([
            'organization' => new OrganizationResource($organization->refresh()),
        ], Response::HTTP_ACCEPTED);
    }
}
