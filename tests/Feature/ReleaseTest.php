<?php

use Ameax\Glitchtip\Release;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->basePath = sys_get_temp_dir().'/glitchtip-release-'.uniqid();
    File::ensureDirectoryExists($this->basePath.'/.git/refs/heads');
    $this->hash = str_repeat('a1b2c3d4e5', 4);
});

afterEach(function () {
    File::deleteDirectory($this->basePath);
});

it('prefers the revision file written by deployer', function () {
    File::put($this->basePath.'/REVISION', "1234567890abcdef1234567890abcdef12345678\n");
    File::put($this->basePath.'/.git/HEAD', $this->hash."\n");

    expect(Release::detect($this->basePath))->toBe('1234567890abcdef1234567890abcdef12345678');
});

it('reads the commit hash of the checked out branch', function () {
    File::put($this->basePath.'/.git/HEAD', "ref: refs/heads/main\n");
    File::put($this->basePath.'/.git/refs/heads/main', $this->hash."\n");

    expect(Release::detect($this->basePath))->toBe($this->hash);
});

it('reads the commit hash from packed refs', function () {
    File::put($this->basePath.'/.git/HEAD', "ref: refs/heads/main\n");
    File::put($this->basePath.'/.git/packed-refs', "# pack-refs with: peeled fully-peeled sorted\n{$this->hash} refs/heads/main\n");

    expect(Release::detect($this->basePath))->toBe($this->hash);
});

it('reads the commit hash of a detached head', function () {
    File::put($this->basePath.'/.git/HEAD', $this->hash."\n");

    expect(Release::detect($this->basePath))->toBe($this->hash);
});

it('ignores invalid revision files', function () {
    File::put($this->basePath.'/REVISION', "<?php echo 'nope';\n");

    expect(Release::fromRevisionFile($this->basePath))->toBeNull();
});

it('returns null without revision file and git checkout', function () {
    expect(Release::detect($this->basePath.'/missing'))->toBeNull();
});
