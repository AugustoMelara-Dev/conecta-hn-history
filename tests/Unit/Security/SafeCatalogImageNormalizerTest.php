<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Infrastructure\Security\SafeCatalogImageNormalizer;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SafeCatalogImageNormalizerTest extends TestCase
{
    public function test_it_reencodes_an_image_and_removes_appended_payloads(): void
    {
        $image = imagecreatetruecolor(2, 2);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        imagedestroy($image);

        self::assertIsString($png);
        $payload = $png."<?php echo 'unsafe'; ?>";

        $result = (new SafeCatalogImageNormalizer)->normalize($payload);

        self::assertSame('image/png', $result['mime']);
        self::assertSame(2, $result['width']);
        self::assertSame(2, $result['height']);
        self::assertStringNotContainsString('<?php', $result['contents']);
        self::assertLessThan(strlen($payload), strlen($result['contents']));
    }

    public function test_it_rejects_non_image_payloads(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new SafeCatalogImageNormalizer)->normalize('<svg onload="alert(1)"></svg>');
    }
}
