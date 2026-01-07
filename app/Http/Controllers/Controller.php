<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Conecta HN API",
    description: "Enterprise API for Conecta HN Multi-tenant SaaS Platform",
    contact: new OA\Contact(email: "tech@conecta.hn")
)]
#[OA\Server(
    url: "/api",
    description: "Main API Server"
)]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT"
)]
abstract class Controller
{
    //
}
