<?php

namespace TCG\Voyager\Tests;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use TCG\Voyager\Models\DataRow;
use TCG\Voyager\Models\DataType;
use TCG\Voyager\Models\Page;
use TCG\Voyager\Support\SafeUpload;

/**
 * CVE-2024-55417: the media manager and the BREAD upload handlers trusted the
 * client-supplied extension, so an image polyglot named x.php landed under the
 * public disk and was executed by PHP-FPM.
 */
class MediaUploadSecurityTest extends TestCase
{
    protected $storage;

    public function setUp(): void
    {
        parent::setUp();

        Auth::loginUsingId(1);

        // Worst case: no MIME restriction at all.
        config()->set('voyager.media.allowed_mimetypes', '*');

        $this->storage = Storage::fake(config('voyager.storage.disk'));
    }

    public static function dangerousNames(): array
    {
        return [
            'php'          => ['shell.php'],
            'upper case'   => ['shell.PHP'],
            'mixed case'   => ['shell.PhP'],
            'phtml'        => ['shell.phtml'],
            'phar'         => ['shell.phar'],
            'php5'         => ['shell.php5'],
            'double ext'   => ['shell.php.jpg'],
            'htaccess'     => ['.htaccess'],
            'user.ini'     => ['.user.ini'],
        ];
    }

    #[DataProvider('dangerousNames')]
    public function testMediaUploadRejectsExecutableExtensions(string $name)
    {
        $response = $this->media('upload', [
            'upload_path' => '/',
            'file'        => UploadedFile::fake()->image($name),
        ]);

        $response->seeJson(['success' => false, 'message' => __('voyager::media.error_extension_not_allowed')]);
        $this->assertSame([], $this->storage->allFiles());
    }

    public function testMediaUploadRejectsDangerousFilenameField()
    {
        $response = $this->media('upload', [
            'upload_path' => '/',
            'filename'    => 'shell.php',
            'file'        => UploadedFile::fake()->image('innocent.jpg'),
        ]);

        $response->seeJson(['success' => false]);
        $this->assertSame([], $this->storage->allFiles());
    }

    public function testMediaUploadRejectsFilenameFieldWithPath()
    {
        $response = $this->media('upload', [
            'upload_path' => '/',
            'filename'    => '../escape',
            'file'        => UploadedFile::fake()->image('innocent.jpg'),
        ]);

        $response->seeJson(['success' => false]);
        $this->assertSame([], $this->storage->allFiles());
    }

    public function testMediaUploadRejectsTraversalInUploadPath()
    {
        $response = $this->media('upload', [
            'upload_path' => '/../../public',
            'file'        => UploadedFile::fake()->image('photo.jpg'),
        ]);

        $response->seeJson(['success' => false, 'message' => __('voyager::media.error_invalid_path')]);
        $this->assertSame([], $this->storage->allFiles());
    }

    public static function safeFiles(): array
    {
        return [
            'jpg' => ['photo.jpg', true],
            'png' => ['photo.png', true],
            'pdf' => ['document.pdf', false],
        ];
    }

    #[DataProvider('safeFiles')]
    public function testMediaUploadAcceptsNormalFiles(string $name, bool $isImage)
    {
        $file = $isImage ? UploadedFile::fake()->image($name) : UploadedFile::fake()->create($name, 1, 'application/pdf');

        $response = $this->media('upload', [
            'upload_path' => '/',
            'file'        => $file,
        ]);

        $response->seeJson(['success' => true]);
        $this->storage->assertExists($name);
    }

    public function testMediaUploadHonoursAllowedExtensionsConfig()
    {
        config()->set('voyager.media.allowed_extensions', ['png']);

        $this->media('upload', [
            'upload_path' => '/',
            'file'        => UploadedFile::fake()->image('photo.jpg'),
        ])->seeJson(['success' => false]);

        $this->assertSame([], $this->storage->allFiles());
    }

    public function testRenameToDangerousExtensionIsRejected()
    {
        $this->storage->put('photo.jpg', 'x');

        $this->media('rename', [
            'folder_location' => '',
            'filename'        => 'photo.jpg',
            'new_filename'    => 'photo.php.jpg',
        ])->seeJson(['success' => false, 'error' => __('voyager::media.error_extension_not_allowed')]);

        $this->storage->assertExists('photo.jpg');
        $this->storage->assertMissing('photo.php.jpg');
    }

    public function testRenameFromDangerousExtensionIsRejected()
    {
        $this->storage->put('shell.php', 'x');

        $this->media('rename', [
            'folder_location' => '',
            'filename'        => 'shell.php',
            'new_filename'    => 'other.php',
        ])->seeJson(['success' => false]);

        $this->storage->assertExists('shell.php');
        $this->storage->assertMissing('other.php');
    }

    public function testRenameRejectsTraversal()
    {
        $this->storage->put('photo.jpg', 'x');

        $this->media('rename', [
            'folder_location' => '../..',
            'filename'        => 'photo.jpg',
            'new_filename'    => 'moved.jpg',
        ])->seeJson(['success' => false, 'error' => __('voyager::media.error_invalid_path')]);

        $this->media('rename', [
            'folder_location' => '',
            'filename'        => 'photo.jpg',
            'new_filename'    => '../moved.jpg',
        ])->seeJson(['success' => false]);

        $this->storage->assertExists('photo.jpg');
    }

    public function testRenameStillWorks()
    {
        $this->storage->put('photo.jpg', 'x');

        $this->media('rename', [
            'folder_location' => '',
            'filename'        => 'photo.jpg',
            'new_filename'    => 'renamed.jpg',
        ])->seeJson(['success' => true]);

        $this->storage->assertExists('renamed.jpg');
    }

    public function testNewFolderRejectsTraversal()
    {
        $this->media('new_folder', ['new_folder' => '/../evil'])
            ->seeJson(['success' => false, 'error' => __('voyager::media.error_invalid_path')]);

        $this->media('new_folder', ['new_folder' => '/fine'])
            ->seeJson(['success' => true]);
    }

    public function testDeleteRejectsTraversal()
    {
        $this->storage->put('keep.jpg', 'x');

        $this->media('delete', [
            'path'  => '/sub/..',
            'files' => [['name' => 'keep.jpg', 'type' => 'file']],
        ])->seeJson(['success' => false, 'error' => __('voyager::media.error_invalid_path')]);

        $this->media('delete', [
            'path'  => '/',
            'files' => [['name' => '../keep.jpg', 'type' => 'file']],
        ])->seeJson(['success' => false]);

        $this->storage->assertExists('keep.jpg');
    }

    public function testMoveRejectsTraversal()
    {
        $this->storage->put('sub/photo.jpg', 'x');

        $this->media('move', [
            'path'        => '/sub',
            'destination' => '/sub/../../outside',
            'files'       => [['name' => 'photo.jpg', 'type' => 'file']],
        ])->seeJson(['success' => false, 'error' => __('voyager::media.error_invalid_path')]);

        $this->storage->assertExists('sub/photo.jpg');
    }

    public function testMoveToParentStillWorks()
    {
        $this->storage->put('sub/photo.jpg', 'x');

        // The media manager sends "/../" to move into the parent folder.
        $this->media('move', [
            'path'        => '/sub',
            'destination' => '/../',
            'files'       => [['name' => 'photo.jpg', 'type' => 'file']],
        ])->seeJson(['success' => true]);

        $this->storage->assertExists('photo.jpg');
    }

    public function testCropRejectsDangerousNamesAndTraversal()
    {
        $this->storage->put('shell.php', 'x');

        $this->media('crop', [
            'upload_path'     => '/',
            'originImageName' => 'shell.php',
            'createMode'      => 'true',
            'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1,
        ])->seeJson(['success' => false]);

        $this->media('crop', [
            'upload_path'     => '/../',
            'originImageName' => 'photo.jpg',
            'createMode'      => 'true',
            'x' => 0, 'y' => 0, 'width' => 1, 'height' => 1,
        ])->seeJson(['success' => false, 'message' => __('voyager::media.error_invalid_path')]);

        $this->assertSame(['shell.php'], $this->storage->allFiles());
    }

    public static function breadTypes(): array
    {
        return [
            'file'            => ['file'],
            'image'           => ['image'],
            'multiple_images' => ['multiple_images'],
        ];
    }

    #[DataProvider('breadTypes')]
    public function testBreadUploadRejectsExecutableExtensions(string $type)
    {
        $data_type_id = DataType::where('slug', 'pages')->first()->id;
        $data_row = DataRow::where(['data_type_id' => $data_type_id, 'field' => 'image'])->first();
        $data_row->type = $type;
        $data_row->details = $type === 'file' ? '' : json_decode('{"preserveFileUploadName":true}');
        $data_row->save();

        $file = UploadedFile::fake()->image('shell.php');
        $this->call('POST', route('voyager.pages.store'), [
            'author_id' => 1,
            'title'     => 'Upload',
            'slug'      => 'upload-shell',
            'status'    => 'ACTIVE',
        ], [], [
            'image' => $type === 'image' ? $file : [$file],
        ]);

        $this->assertNull(Page::where('slug', 'upload-shell')->first());
        $this->assertSame([], $this->storage->allFiles());
    }

    public function testRichTextUploadRejectsExecutableExtensions()
    {
        $this->call('POST', route('voyager.upload'), [
            'type_slug' => 'pages',
        ], [], [
            'image' => UploadedFile::fake()->image('shell.php'),
        ]);

        $this->assertSame([], $this->storage->allFiles());
    }

    /**
     * POST to a media manager route, sending UploadedFile values as files.
     */
    private function media(string $action, array $data)
    {
        $files = array_filter($data, fn ($value) => $value instanceof UploadedFile);

        $this->call('POST', route('voyager.media.'.$action), array_diff_key($data, $files), [], $files);

        return $this;
    }

    public function testSafeUploadHelper()
    {
        $this->assertFalse(SafeUpload::isAllowedFileName('x.pHp7'));
        $this->assertFalse(SafeUpload::isAllowedFileName('x.php.'));
        $this->assertFalse(SafeUpload::isAllowedFileName('x.jsp'));
        $this->assertFalse(SafeUpload::isAllowedFileName("x.jpg\0.php"));
        $this->assertFalse(SafeUpload::isAllowedFileName('dir/x.jpg'));
        $this->assertFalse(SafeUpload::isAllowedFileName('..'));
        $this->assertTrue(SafeUpload::isAllowedFileName('php.jpg'));
        $this->assertTrue(SafeUpload::isAllowedFileName('Photo.JPG'));
        $this->assertTrue(SafeUpload::isAllowedFileName('README'));

        $this->assertTrue(SafeUpload::isSafePath('/'));
        $this->assertTrue(SafeUpload::isSafePath(''));
        $this->assertTrue(SafeUpload::isSafePath(null));
        $this->assertTrue(SafeUpload::isSafePath('/a/b..c/'));
        $this->assertFalse(SafeUpload::isSafePath('a/../b'));
        $this->assertFalse(SafeUpload::isSafePath('a\\..\\b'));
        $this->assertFalse(SafeUpload::isSafePath("a\0b"));
        $this->assertFalse(SafeUpload::isSafePath('C:\\Windows'));
        $this->assertFalse(SafeUpload::isSafePath(['a']));
    }
}
