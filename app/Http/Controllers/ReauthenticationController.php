<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReauthenticationRequest;
use App\Services\ReauthenticationService;
use Illuminate\Http\JsonResponse;

/**
 * Confirmação de identidade avulsa, usada antes de uma ação sensível que não
 * carrega um campo de senha próprio — hoje, iniciar a ativação do MFA.
 *
 * O ReauthenticationRequest já resolve os dois modos: exige current_password
 * de quem tem senha e devolve 423 com a URL do provedor para quem não tem.
 */
class ReauthenticationController extends Controller
{
    public function store(ReauthenticationRequest $request, ReauthenticationService $service): JsonResponse
    {
        $service->markConfirmed($request->session());

        return response()->json(['confirmed' => true]);
    }
}
