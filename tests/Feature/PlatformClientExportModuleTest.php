<?php

namespace Tests\Feature;

use App\Models\ClientExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

class PlatformClientExportModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(storage_path('app/backups/client-exports'));
        File::deleteDirectory(storage_path('app/backups/.tmp'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/backups/client-exports'));
        File::deleteDirectory(storage_path('app/backups/.tmp'));

        parent::tearDown();
    }

    public function test_super_admin_can_open_client_export_screen(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.client-exports.index'))
            ->assertOk()
            ->assertSee('Per-Client Export Control')
            ->assertSee('Create A Client Export');
    }

    public function test_non_super_admin_cannot_open_client_export_screen(): void
    {
        [$clientId, $branchId] = $this->createClientWithBranch('Normal Client', 'Main Branch');

        $user = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.platform.client-exports.index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_create_and_review_client_export(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive support is not available.');
        }

        $superAdmin = $this->createSuperAdmin();
        [$clientId, $branchId] = $this->createClientWithBranch('Exported Pharmacy', 'Main Branch');

        $tenantUser = User::factory()->create([
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Export Role',
            'code' => 'EXP-ROLE-' . $clientId,
            'description' => 'Role used for client export testing.',
            'is_system_role' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'exports',
            'action_name' => 'review',
            'permission_key' => 'exports.review.' . $clientId,
            'description' => 'Permission used for client export testing.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $tenantUser->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_settings')->insert([
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($superAdmin)
            ->post(route('admin.platform.client-exports.store'), [
                'client_id' => $clientId,
                'notes' => 'Archive before handover.',
            ]);

        $clientExport = ClientExport::query()->latest('id')->first();

        $this->assertNotNull($clientExport);
        $response->assertRedirect(route('admin.platform.client-exports.show', $clientExport));
        $this->assertSame('Archive before handover.', $clientExport->notes);
        $this->assertTrue($clientExport->fileExists());
        $this->assertFileExists($clientExport->absolutePath());

        $manifest = $this->readManifest($clientExport);
        $tableNames = collect(data_get($manifest, 'database.tables', []))->pluck('name');

        $this->assertSame($clientId, data_get($manifest, 'client.id'));
        $this->assertSame('Exported Pharmacy', data_get($manifest, 'client.name'));
        $this->assertTrue($tableNames->contains('clients'));
        $this->assertTrue($tableNames->contains('branches'));
        $this->assertTrue($tableNames->contains('users'));
        $this->assertTrue($tableNames->contains('roles'));
        $this->assertTrue($tableNames->contains('user_roles'));
        $this->assertTrue($tableNames->contains('permissions'));
        $this->assertTrue($tableNames->contains('role_permissions'));

        $this->actingAs($superAdmin)
            ->get(route('admin.platform.client-exports.show', $clientExport))
            ->assertOk()
            ->assertSee($clientExport->filename)
            ->assertSee('Exported Pharmacy')
            ->assertSee('Database Table Manifest');
    }

    public function test_super_admin_can_import_client_export_as_new_clone(): void
    {
        if (!class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive support is not available.');
        }

        $superAdmin = $this->createSuperAdmin();
        [$clientId, $branchId] = $this->createClientWithBranch('Original Pharmacy', 'Main Branch');

        $tenantUser = User::factory()->create([
            'name' => 'Original User',
            'email' => 'pharmacy@example.com',
            'client_id' => $clientId,
            'branch_id' => $branchId,
            'is_active' => true,
            'is_super_admin' => false,
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'client_id' => $clientId,
            'name' => 'Original Role',
            'code' => 'ORIGINAL-ROLE',
            'description' => 'Role used for client import testing.',
            'is_system_role' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = DB::table('permissions')->insertGetId([
            'module_name' => 'imports',
            'action_name' => 'use',
            'permission_key' => 'imports.use.original',
            'description' => 'Permission used for client import testing.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'user_id' => $tenantUser->id,
            'role_id' => $roleId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('client_settings')->insert([
            'client_id' => $clientId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($superAdmin)
            ->post(route('admin.platform.client-exports.store'), [
                'client_id' => $clientId,
                'notes' => 'Archive before clone import.',
            ])
            ->assertRedirect();

        $clientExport = ClientExport::query()->latest('id')->firstOrFail();

        $response = $this->actingAs($superAdmin)
            ->post(route('admin.platform.client-exports.import', $clientExport), [
                'restored_client_name' => 'Original Pharmacy Clone',
                'import_confirmation' => $clientExport->filename,
                'activate_imported_client' => 0,
            ]);

        $importedClient = DB::table('clients')->where('name', 'Original Pharmacy Clone')->first();

        $this->assertNotNull($importedClient);
        $response->assertRedirect(route('admin.platform.clients.edit', $importedClient->id));
        $this->assertSame(0, (int) $importedClient->is_active);

        $importedUser = DB::table('users')
            ->where('client_id', $importedClient->id)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($importedUser);
        $this->assertNotSame('pharmacy@example.com', $importedUser->email);
        $this->assertStringContainsString('+restored', $importedUser->email);

        $importedRole = DB::table('roles')
            ->where('client_id', $importedClient->id)
            ->orderBy('id')
            ->first();

        $this->assertNotNull($importedRole);
        $this->assertNotSame('ORIGINAL-ROLE', $importedRole->code);
        $this->assertStringContainsString('-IMP', $importedRole->code);

        $this->assertDatabaseHas('user_roles', [
            'user_id' => $importedUser->id,
            'role_id' => $importedRole->id,
        ]);

        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $importedRole->id,
            'permission_id' => $permissionId,
        ]);
    }

    private function readManifest(ClientExport $clientExport): array
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($clientExport->absolutePath()) === true);
        $manifestContents = $zip->getFromName('manifest.json');
        $zip->close();

        $this->assertNotFalse($manifestContents);

        return json_decode($manifestContents, true) ?? [];
    }

    private function createSuperAdmin(): User
    {
        [$homeClientId, $homeBranchId] = $this->createClientWithBranch('Owner Home', 'Owner Branch');

        return User::factory()->create([
            'client_id' => $homeClientId,
            'branch_id' => $homeBranchId,
            'is_active' => true,
            'is_super_admin' => true,
        ]);
    }

    private function createClientWithBranch(string $clientName, string $branchName, string $businessMode = 'both'): array
    {
        $clientId = DB::table('clients')->insertGetId([
            'name' => $clientName,
            'business_mode' => $businessMode,
            'is_active' => true,
            'is_platform_sandbox' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'client_id' => $clientId,
            'name' => $branchName,
            'code' => strtoupper(substr($branchName, 0, 3)) . $clientId,
            'business_mode' => 'inherit',
            'is_main' => true,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$clientId, $branchId];
    }
}
