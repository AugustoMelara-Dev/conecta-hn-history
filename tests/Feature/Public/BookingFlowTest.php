<?php

declare(strict_types=1);

namespace Tests\Feature\Public;

use App\Http\Controllers\Public\BookingController;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class BookingFlowTest extends TestCase
{
    #[Test]
    public function booking_controller_exists(): void
    {
        self::assertTrue(class_exists(BookingController::class));
    }

    #[Test]
    public function booking_controller_exposes_the_expected_methods(): void
    {
        $reflection = new ReflectionClass(BookingController::class);

        self::assertTrue($reflection->hasMethod('index'));
        self::assertTrue($reflection->hasMethod('store'));
        self::assertTrue($reflection->hasMethod('getProviders'));
        self::assertTrue($reflection->hasMethod('getSlots'));
        self::assertTrue($reflection->hasMethod('success'));
    }

    #[Test]
    public function tenant_model_exposes_booking_relationships(): void
    {
        $reflection = new ReflectionClass(Tenant::class);

        self::assertTrue($reflection->hasMethod('services'));
        self::assertTrue($reflection->hasMethod('serviceProviders'));
        self::assertTrue($reflection->hasMethod('appointments'));
    }

    #[Test]
    public function booking_models_expose_the_expected_scopes_and_helpers(): void
    {
        $serviceReflection = new ReflectionClass(Service::class);
        $providerReflection = new ReflectionClass(ServiceProvider::class);

        self::assertTrue($serviceReflection->hasMethod('scopeActive'));
        self::assertTrue($providerReflection->hasMethod('scopeActive'));
        self::assertTrue($providerReflection->hasMethod('isAvailableAt'));
    }

    #[Test]
    public function appointment_status_constants_match_the_expected_contract(): void
    {
        self::assertSame('pending', Appointment::STATUS_PENDING);
        self::assertSame('confirmed', Appointment::STATUS_CONFIRMED);
        self::assertSame('cancelled', Appointment::STATUS_CANCELLED);
        self::assertSame('completed', Appointment::STATUS_COMPLETED);
    }

    #[Test]
    public function react_booking_pages_exist(): void
    {
        $indexPath = base_path('resources/js/Pages/Public/Book/Index.jsx');
        $successPath = base_path('resources/js/Pages/Public/Book/Success.jsx');

        self::assertFileExists($indexPath);
        self::assertFileExists($successPath);
    }

    #[Test]
    public function booking_routes_are_declared_in_the_public_routes_file(): void
    {
        $routeFile = base_path('routes/public.php');
        $contents = file_get_contents($routeFile);

        self::assertIsString($contents);
        self::assertStringContainsString('BookingController', $contents);
        self::assertStringContainsString('public.booking.index', $contents);
        self::assertStringContainsString('public.booking.store', $contents);
        self::assertStringContainsString('public.booking.success', $contents);
    }
}
