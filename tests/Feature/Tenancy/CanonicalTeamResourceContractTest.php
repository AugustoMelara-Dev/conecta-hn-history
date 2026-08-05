<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CanonicalTeamResourceContractTest extends TestCase
{
    #[Test]
    public function filament_team_resource_uses_canonical_memberships_and_invitation_writer(): void
    {
        $resource = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Filament/App/Resources/TenantUserResource.php',
        );
        $createPage = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Filament/App/Resources/TenantUserResource/Pages/CreateTenantUser.php',
        );
        $invitationResource = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Filament/App/Resources/TenantInvitationResource.php',
        );
        $editPage = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Filament/App/Resources/TenantUserResource/Pages/EditTenantUser.php',
        );

        self::assertStringContainsString('use App\Models\TenantMembership;', $resource);
        self::assertStringContainsString('protected static ?string $model = TenantMembership::class;', $resource);
        self::assertStringNotContainsString('use App\Models\TenantUser;', $resource);
        self::assertStringContainsString('TenantInvitationService', $createPage);
        self::assertStringContainsString('handleRecordCreation', $createPage);
        self::assertStringNotContainsString("\$data['tenant_id']", $createPage);
        self::assertStringContainsString("TextInput::make('email')", $resource);
        self::assertStringNotContainsString("Select::make('user_id')", $resource);
        self::assertStringContainsString('TenantInvitationNotification', $createPage);
        self::assertStringContainsString('TenantInvitationService', $invitationResource);
        self::assertStringContainsString("Action::make('reissue')", $invitationResource);
        self::assertStringContainsString("Action::make('revoke')", $invitationResource);
        self::assertStringContainsString("->where('tenant_id', Filament::getTenant()->getKey())", $invitationResource);
        self::assertStringContainsString('TenantMembershipService', $editPage);
        self::assertStringContainsString('handleRecordUpdate', $editPage);
        self::assertStringNotContainsString('DeleteAction', $editPage);
        self::assertStringContainsString("Action::make('suspend')", $resource);
        self::assertStringContainsString("Action::make('revoke')", $resource);
        self::assertStringContainsString('public static function getEloquentQuery()', $resource);
        self::assertStringContainsString("->where('tenant_id', \$tenant->getKey())", $resource);
    }
}
