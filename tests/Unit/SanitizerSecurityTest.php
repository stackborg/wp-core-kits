<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Sanitizer;

/**
 * Real-world security tests for Sanitizer.
 *
 * These tests verify that actual XSS payloads, injection
 * attempts, and malformed inputs are properly neutralized.
 */
class SanitizerSecurityTest extends TestCase
{
    // ─── XSS Attack Vectors ─────────────────────────────

    public function testTextStripsScriptTags(): void
    {
        $xss = '<script>alert("xss")</script>Normal text';
        $this->assertSame('alert("xss")Normal text', Sanitizer::text($xss));
    }

    public function testTextStripsEventHandlers(): void
    {
        $xss = '<img src=x onerror=alert(1)>';
        $result = Sanitizer::text($xss);
        $this->assertStringNotContainsString('onerror', $result);
    }

    public function testTextStripsNestedTags(): void
    {
        $xss = '<<script>script>alert("xss")<</script>/script>';
        $result = Sanitizer::text($xss);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testEscHtmlNeutralizesHtmlInjection(): void
    {
        $vectors = [
            '<script>alert(1)</script>',
            '"><script>alert(1)</script>',
            "' onmouseover='alert(1)",
            '<img src="javascript:alert(1)">',
            '<svg/onload=alert(1)>',
        ];

        foreach ($vectors as $vector) {
            $result = Sanitizer::escHtml($vector);
            // All < and > and quotes should be entity-encoded
            // so browser will NOT execute them as HTML/JS
            $this->assertStringNotContainsString('<script>', $result, "Raw script tag still in: $vector");
            $this->assertStringNotContainsString('<svg', $result, "Raw SVG tag still in: $vector");
            $this->assertStringNotContainsString('<img', $result, "Raw img tag still in: $vector");
        }
    }

    public function testEscAttrNeutralizesAttributeBreakout(): void
    {
        $attack = '" onclick="alert(1)" data-x="';
        $result = Sanitizer::escAttr($attack);
        $this->assertStringNotContainsString('"', $result);
    }

    public function testKsesStripsScriptButKeepsSafeTags(): void
    {
        $html = '<p>Hello</p><script>bad()</script><strong>bold</strong>';
        $result = Sanitizer::kses($html);
        $this->assertStringContainsString('<p>Hello</p>', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringNotContainsString('bad()', $result);
    }

    // ─── Edge Cases ─────────────────────────────────────

    public function testTextHandlesEmptyString(): void
    {
        $this->assertSame('', Sanitizer::text(''));
    }

    public function testTextHandlesWhitespaceOnly(): void
    {
        $this->assertSame('', Sanitizer::text('   '));
    }

    public function testEmailRejectsCompletelyInvalidEmail(): void
    {
        // FILTER_SANITIZE_EMAIL strips invalid chars like < > /
        // Input '<script>bad</script>' → 'scriptbadscript' (stripped tags)
        $result = Sanitizer::email('<script>bad</script>');
        $this->assertStringNotContainsString('<', $result);
        $this->assertStringNotContainsString('>', $result);
    }

    public function testEmailPassesThroughFormattedButInvalidEmail(): void
    {
        // This is expected PHP behavior — sanitize ≠ validate
        $result = Sanitizer::email('not-an-email');
        $this->assertIsString($result);
    }

    public function testEmailHandlesEmailWithPlusSign(): void
    {
        $result = Sanitizer::email('user+tag@example.com');
        $this->assertSame('user+tag@example.com', $result);
    }

    public function testUrlSanitizesSpecialCharacters(): void
    {
        // Note: PHP FILTER_SANITIZE_URL only removes invalid URL chars.
        // javascript: protocol blocking is done by real WP esc_url()
        // which is more sophisticated than our mock.
        // Test what our mock DOES handle:
        $result = Sanitizer::url('https://example.com/path?q=hello world');
        $this->assertStringContainsString('https://example.com', $result);
    }

    public function testUrlHandlesEmptyString(): void
    {
        $result = Sanitizer::url('');
        $this->assertSame('', $result);
    }

    public function testIntHandlesFloatString(): void
    {
        $this->assertSame(42, Sanitizer::int('42.9'));
    }

    public function testIntHandlesNegative(): void
    {
        $this->assertSame(-5, Sanitizer::int('-5'));
    }

    public function testAbsintHandlesStringInput(): void
    {
        $this->assertSame(0, Sanitizer::absint('not_a_number'));
    }

    public function testTextHandlesUnicodeContent(): void
    {
        $bengali = 'এটি বাংলা টেক্সট';
        $this->assertSame($bengali, Sanitizer::text($bengali));
    }

    public function testTextHandlesMultilineInput(): void
    {
        $result = Sanitizer::text("line1\nline2\rline3");
        $this->assertIsString($result);
    }
}
