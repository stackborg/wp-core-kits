<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Sanitizer;

class SanitizerTest extends TestCase
{
    public function testTextStripsHtml(): void
    {
        $this->assertSame('hello world', Sanitizer::text('<b>hello</b> world'));
    }

    public function testEmailSanitizesInvalidChars(): void
    {
        $result = Sanitizer::email('user@example.com');
        $this->assertSame('user@example.com', $result);
    }

    public function testUrlSanitizesUrl(): void
    {
        $result = Sanitizer::url('https://example.com/path?q=1');
        $this->assertSame('https://example.com/path?q=1', $result);
    }

    public function testIntCastsToInteger(): void
    {
        $this->assertSame(42, Sanitizer::int('42'));
        $this->assertSame(0, Sanitizer::int('abc'));
    }

    public function testAbsintReturnsAbsoluteInteger(): void
    {
        $this->assertSame(5, Sanitizer::absint(-5));
        $this->assertSame(5, Sanitizer::absint(5));
    }

    public function testEscHtmlEscapesHtmlEntities(): void
    {
        $result = Sanitizer::escHtml('<script>alert("xss")</script>');
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testEscAttrEscapesAttributes(): void
    {
        $result = Sanitizer::escAttr('" onmouseover="alert(1)"');
        $this->assertStringNotContainsString('"', $result);
    }

    public function testKsesAllowsSafeHtml(): void
    {
        $result = Sanitizer::kses('<strong>bold</strong><script>bad</script>');
        $this->assertStringContainsString('<strong>', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }
}
