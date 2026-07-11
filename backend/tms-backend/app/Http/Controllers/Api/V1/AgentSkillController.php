<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AssignAgentSkillsRequest;
use App\Http\Resources\UserResource;
use App\Services\Users\UserManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class AgentSkillController extends Controller
{
    public function __construct(private readonly UserManagementService $userService) {}

    public function __invoke(AssignAgentSkillsRequest $request, string $user): JsonResponse
    {
        $target = $this->userService->find($request->user(), $user);

        $this->authorize('manageSkills', $target);

        $target = $this->userService->syncSkills($request->user(), $target, $request->validated('ticket_type_ids'));

        return ApiResponse::success(new UserResource($target), __('messages.users.skills_updated'));
    }
}
