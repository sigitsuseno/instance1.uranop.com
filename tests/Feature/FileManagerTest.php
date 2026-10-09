<?php

namespace Tests\Feature;

use App\Modules\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/file-manager';

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeder tidak dijalankan oleh RefreshDatabase, jadi peran dibuat manual.
        Role::create(['name' => 'superadmin', 'guard_name' => 'web']);
        Role::create(['name' => 'hrmanager', 'guard_name' => 'web']);

        $this->superadmin = User::create([
            'name' => 'Super Admin',
            'email' => 'superadmin@test.com',
            'password' => bcrypt('password'),
        ]);
        $this->superadmin->assignRole('superadmin');

        Storage::fake('file_manager');
    }

    private function makeUser(string $role = 'hrmanager'): User
    {
        $user = User::create([
            'name' => 'Pengguna '.$role,
            'email' => $role.'@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_superadmin_can_browse_root(): void
    {
        Storage::disk('file_manager')->put('catatan.txt', 'halo');

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/browse');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['type', 'name', 'path', 'size', 'category', 'preview_kind']],
                'folders',
                'breadcrumbs' => [['name', 'path']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('data.0.name', 'catatan.txt')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_unauthenticated_request_is_unauthorized(): void
    {
        $this->getJson(self::BASE.'/browse')->assertStatus(401);
    }

    public function test_non_superadmin_is_forbidden(): void
    {
        $this->actingAs($this->makeUser('hrmanager'), 'sanctum')
            ->getJson(self::BASE.'/browse')
            ->assertStatus(403);
    }

    public function test_can_create_and_browse_folder(): void
    {
        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/folders', ['name' => 'Dokumen'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Dokumen');

        Storage::disk('file_manager')->assertExists('Dokumen');

        $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/browse')
            ->assertStatus(200)
            ->assertJsonPath('folders.0.name', 'Dokumen');
    }

    public function test_folder_name_collision_is_rejected(): void
    {
        Storage::disk('file_manager')->makeDirectory('Dokumen');

        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/folders', ['name' => 'Dokumen'])
            ->assertStatus(422);
    }

    public function test_rejects_dotdot_traversal(): void
    {
        $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/browse?path='.urlencode('../../etc'))
            ->assertStatus(422);
    }

    public function test_rejects_backslash_traversal(): void
    {
        $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/browse?path='.urlencode('..\\..\\rahasia'))
            ->assertStatus(422);
    }

    public function test_rejects_absolute_path(): void
    {
        $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/browse?path='.urlencode('C:\\Windows'))
            ->assertStatus(422);
    }

    public function test_rejects_traversal_on_delete(): void
    {
        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/delete', ['paths' => ['../../berkas.txt']])
            ->assertStatus(422);
    }

    public function test_rejects_illegal_folder_name(): void
    {
        foreach (['con', 'a/b', 'laporan.', 'nama:dilarang'] as $name) {
            $this->actingAs($this->superadmin, 'sanctum')
                ->postJson(self::BASE.'/folders', ['name' => $name])
                ->assertStatus(422);
        }
    }

    public function test_can_upload_file_into_folder(): void
    {
        Storage::disk('file_manager')->makeDirectory('Dokumen');

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->post(self::BASE.'/upload', [
                'path' => 'Dokumen',
                'files' => [UploadedFile::fake()->create('laporan.pdf', 100, 'application/pdf')],
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.stored.0.name', 'laporan.pdf');

        Storage::disk('file_manager')->assertExists('Dokumen/laporan.pdf');
    }

    public function test_upload_blocks_executable_extension(): void
    {
        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->post(self::BASE.'/upload', [
                'files' => [UploadedFile::fake()->create('shell.php', 1, 'application/x-php')],
            ]);

        $response->assertStatus(422)->assertJsonPath('success', false);

        Storage::disk('file_manager')->assertMissing('shell.php');
    }

    public function test_upload_suffixes_colliding_name(): void
    {
        $file = fn () => UploadedFile::fake()->create('laporan.pdf', 10, 'application/pdf');

        $this->actingAs($this->superadmin, 'sanctum')
            ->post(self::BASE.'/upload', ['files' => [$file()]])
            ->assertStatus(201);

        $this->actingAs($this->superadmin, 'sanctum')
            ->post(self::BASE.'/upload', ['files' => [$file()]])
            ->assertStatus(201)
            ->assertJsonPath('data.stored.0.name', 'laporan (1).pdf');

        Storage::disk('file_manager')->assertExists('laporan.pdf');
        Storage::disk('file_manager')->assertExists('laporan (1).pdf');
    }

    public function test_can_rename_and_move_file(): void
    {
        Storage::disk('file_manager')->put('laporan.pdf', 'isi');
        Storage::disk('file_manager')->makeDirectory('Arsip');

        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/rename', ['path' => 'laporan.pdf', 'name' => 'laporan-final.pdf'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'laporan-final.pdf');

        Storage::disk('file_manager')->assertMissing('laporan.pdf');
        Storage::disk('file_manager')->assertExists('laporan-final.pdf');

        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/move', ['paths' => ['laporan-final.pdf'], 'target' => 'Arsip'])
            ->assertStatus(200);

        Storage::disk('file_manager')->assertMissing('laporan-final.pdf');
        Storage::disk('file_manager')->assertExists('Arsip/laporan-final.pdf');
    }

    public function test_cannot_move_folder_into_itself(): void
    {
        Storage::disk('file_manager')->makeDirectory('A');

        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/move', ['paths' => ['A'], 'target' => 'A'])
            ->assertStatus(422);
    }

    public function test_can_delete_folder_recursively(): void
    {
        Storage::disk('file_manager')->put('Lama/di-dalam.txt', 'isi');

        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/delete', ['paths' => ['Lama']])
            ->assertStatus(200);

        Storage::disk('file_manager')->assertMissing('Lama/di-dalam.txt');
        $this->assertDirectoryDoesNotExist(Storage::disk('file_manager')->path('Lama'));
    }

    public function test_cannot_delete_root(): void
    {
        Storage::disk('file_manager')->put('penting.txt', 'isi');

        $this->actingAs($this->superadmin, 'sanctum')
            ->postJson(self::BASE.'/delete', ['paths' => ['']])
            ->assertStatus(422);

        Storage::disk('file_manager')->assertExists('penting.txt');
    }

    public function test_preview_serves_image_inline(): void
    {
        Storage::disk('file_manager')->put('titik.png', $this->tinyPng());

        $this->actingAs($this->superadmin, 'sanctum')
            ->get(self::BASE.'/preview?path='.urlencode('titik.png'))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_preview_rejects_type_without_preview(): void
    {
        $zip = $this->tinyZip();
        Storage::disk('file_manager')->put('arsip.zip', $zip);

        $this->actingAs($this->superadmin, 'sanctum')
            ->get(self::BASE.'/preview?path='.urlencode('arsip.zip'))
            ->assertStatus(415);
    }

    public function test_download_returns_attachment(): void
    {
        Storage::disk('file_manager')->put('laporan.pdf', 'isi berkas');

        $this->actingAs($this->superadmin, 'sanctum')
            ->get(self::BASE.'/download?path='.urlencode('laporan.pdf'))
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/octet-stream');
    }

    public function test_excel_preview_returns_sheets_and_rows(): void
    {
        Storage::disk('file_manager')->put('data.xlsx', $this->tinyXlsx());

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/excel?path='.urlencode('data.xlsx'));

        $response->assertStatus(200)
            ->assertJsonPath('data.sheets.0.name', 'Sheet1')
            ->assertJsonPath('data.sheets.0.rows.0.0', 'Nama')
            ->assertJsonPath('data.sheets.0.rows.1.0', 'Budi')
            ->assertJsonPath('data.sheets.0.truncated', false);
    }

    public function test_excel_preview_rejects_non_spreadsheet(): void
    {
        Storage::disk('file_manager')->put('catatan.txt', 'halo');

        $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/excel?path='.urlencode('catatan.txt'))
            ->assertStatus(422);
    }

    public function test_folder_tree_marks_excluded_subtree(): void
    {
        Storage::disk('file_manager')->makeDirectory('A/B');
        Storage::disk('file_manager')->makeDirectory('C');

        $response = $this->actingAs($this->superadmin, 'sanctum')
            ->getJson(self::BASE.'/tree?exclude='.urlencode('A'));

        $response->assertStatus(200);

        $nodes = collect($response->json('data'))->keyBy('path');

        // Folder yang dipindah beserta seluruh turunannya tidak ditawarkan
        // sebagai tujuan, jadi 'A/B' memang tidak muncul di pohon.
        $this->assertTrue($nodes['A']['disabled']);
        $this->assertArrayNotHasKey('A/B', $nodes->all());
        $this->assertFalse($nodes['C']['disabled']);
    }

    /** PNG 1x1 piksel yang valid. */
    private function tinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='
        );
    }

    private function tinyZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fmzip').'.zip';

        $zip = new \ZipArchive;
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('isi.txt', 'halo');
        $zip->close();

        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }

    private function tinyXlsx(): string
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Sheet1');
        $sheet->fromArray([['Nama', 'NIP'], ['Budi', '12345']], null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'fmxlsx').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $contents = (string) file_get_contents($path);
        @unlink($path);

        return $contents;
    }
}
