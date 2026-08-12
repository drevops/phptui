<?php

declare(strict_types=1);

namespace DrevOps\PhpTui\Tests\Unit\Discovery;

use DrevOps\PhpTui\Discovery\Dotenv;
use DrevOps\PhpTui\Discovery\JsonValue;
use DrevOps\PhpTui\Discovery\PathExists;
use DrevOps\PhpTui\Discovery\Scan;
use DrevOps\PhpTui\Discovery\ScanType;
use org\bovigo\vfs\vfsStream;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests the declarative discovery rule objects.
 */
#[CoversClass(Dotenv::class)]
#[CoversClass(JsonValue::class)]
#[CoversClass(PathExists::class)]
#[CoversClass(Scan::class)]
#[Group('discovery')]
final class DiscoverSpecsTest extends TestCase {

  /**
   * The virtual project directory.
   */
  protected string $dir;

  protected function setUp(): void {
    parent::setUp();
    vfsStream::setup('project', NULL, [
      '.env' => "# comment\n   \nNOEQUALS\nDRUPAL_PROFILE=standard\nSITE_NAME=\"Acme Site\"\nEMPTY=\n",
      'composer.json' => '{"name": "acme/site", "extra": {"drupal": {"webroot": "web"}}}',
      'web' => [
        'sites' => ['default' => ['settings.php' => '<?php']],
        'modules' => ['custom' => ['alpha' => ['alpha.info.yml' => ''], 'beta' => ['beta.info.yml' => '']]],
      ],
      'mixed' => [
        'afile.txt' => 'x',
        'adir' => ['keep' => ''],
      ],
    ]);
    $this->dir = vfsStream::url('project');
  }

  public function testDotenv(): void {
    $this->assertSame('standard', (new Dotenv('DRUPAL_PROFILE'))->discover($this->dir));
    $this->assertSame('Acme Site', (new Dotenv('SITE_NAME'))->discover($this->dir));
    $this->assertSame('', (new Dotenv('EMPTY'))->discover($this->dir));
    $this->assertNull((new Dotenv('MISSING'))->discover($this->dir));
  }

  public function testJsonValue(): void {
    $this->assertSame('acme/site', (new JsonValue('composer.json', 'name'))->discover($this->dir));
    $this->assertSame('web', (new JsonValue('composer.json', 'extra.drupal.webroot'))->discover($this->dir));
    $this->assertNull((new JsonValue('composer.json', 'nope.deep'))->discover($this->dir));
    $this->assertNull((new JsonValue('composer.json', 'extra'))->discover($this->dir));
    $this->assertNull((new JsonValue('missing.json', 'name'))->discover($this->dir));
    $this->assertNull((new JsonValue('', 'name'))->discover($this->dir));
    $this->assertNull((new JsonValue('composer.json', ''))->discover($this->dir));
  }

  public function testPathExists(): void {
    $this->assertTrue((new PathExists('web/sites/default/settings.php'))->discover($this->dir));
    $this->assertFalse((new PathExists('web/nope.php'))->discover($this->dir));
  }

  public function testScan(): void {
    $this->assertSame(['alpha', 'beta'], (new Scan('web/modules/custom', ScanType::Dir))->discover($this->dir));
    // A missing directory is "nothing to discover", not a detected empty list.
    $this->assertNull((new Scan('web/nope'))->discover($this->dir));
    // type=dir skips the file; type=file skips the directory.
    $this->assertSame(['adir'], (new Scan('mixed', ScanType::Dir))->discover($this->dir));
    $this->assertSame(['afile.txt'], (new Scan('mixed', ScanType::File))->discover($this->dir));
    $this->assertSame(['adir', 'afile.txt'], (new Scan('mixed'))->discover($this->dir));
  }

  public function testCleanDirectoryDiscoversNothing(): void {
    vfsStream::setup('empty', structure: ['hollow' => []]);
    $dir = vfsStream::url('empty');

    $this->assertNull((new Dotenv('DRUPAL_PROFILE'))->discover($dir));
    $this->assertNull((new JsonValue('composer.json', 'name'))->discover($dir));
    $this->assertFalse((new PathExists('web'))->discover($dir));
    $this->assertNull((new Scan('web'))->discover($dir));

    // An existing directory with no entries is a real discovery: empty.
    $this->assertSame([], (new Scan('hollow'))->discover($dir));
  }

  public function testToArray(): void {
    $this->assertSame(['dotenv' => 'TZ'], (new Dotenv('TZ'))->toArray());
    $this->assertSame(['json' => ['file' => 'composer.json', 'path' => 'name']], (new JsonValue('composer.json', 'name'))->toArray());
    $this->assertSame(['exists' => 'docker-compose.yml'], (new PathExists('docker-compose.yml'))->toArray());
    $this->assertSame(['scan' => ['dir' => 'modules', 'type' => 'dir']], (new Scan('modules', ScanType::Dir))->toArray());
  }

}
