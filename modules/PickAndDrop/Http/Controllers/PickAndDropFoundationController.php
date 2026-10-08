<?php

namespace Modules\PickAndDrop\Http\Controllers;

use Illuminate\Http\JsonResponse;

class PickAndDropFoundationController
{
    public function status(): JsonResponse
    {
        return response()->json([
            'module' => config('modules.pick_and_drop.name'),
            'version' => config('modules.pick_and_drop.version'),
            'status' => 'ready',
        ]);
    }
}
