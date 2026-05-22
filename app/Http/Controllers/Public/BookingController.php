<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Infrastructure\Booking\HttpPublicBookingResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Response;

final class BookingController extends Controller
{
    public function __construct(private readonly HttpPublicBookingResponder $responder) {}

    public function index(Request $request): Response
    {
        return $this->responder->index($request);
    }

    public function getProviders(Request $request, mixed $service): JsonResponse
    {
        return $this->responder->getProviders($request, $service);
    }

    public function getSlots(Request $request): JsonResponse
    {
        return $this->responder->getSlots($request);
    }

    public function store(Request $request): mixed
    {
        return $this->responder->store($request);
    }

    public function success(Request $request): Response
    {
        return $this->responder->success($request);
    }
}
