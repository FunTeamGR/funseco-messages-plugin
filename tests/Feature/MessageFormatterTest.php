<?php

namespace Funseco\Messages\Tests\Feature;

use Funseco\Core\Services\Messages\MessageFormatter;
use Funseco\Core\SharedKernel\Enums\MessageType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Funseco\Messages\Tests\TestCase;

class MessageFormatterTest extends TestCase
{
    use RefreshDatabase;

    private function f(): MessageFormatter
    {
        return app(MessageFormatter::class);
    }

    public function test_info_style_matches_legacy_output(): void
    {
        $this->assertSame(format_colors('{#message}x'), $this->f()->format(MessageType::Info, 'x'));
        $this->assertSame(format_colors('{#admin}x'), $this->f()->format(MessageType::Warning, 'x'));
        $this->assertSame(format_colors('{#error}x'), $this->f()->format(MessageType::Error, 'x'));
    }

    public function test_topic_token_sets_body_colour_and_style_override_applies(): void
    {
        $this->assertSame(format_colors('{#music}x'), $this->f()->format(MessageType::Info, 'x', 'music'));
        $this->f()->styles()->set(MessageType::Info, 'prefix', '{#server}> ');
        $this->assertSame(format_colors('{#server}> {#message}x'), $this->f()->format(MessageType::Info, 'x'));
    }

    public function test_unknown_placeholder_kept_literal_and_logged(): void
    {
        Log::spy();
        $this->assertSame('Hi {nope} bob', $this->f()->render('Hi {nope} {n}', ['n' => 'bob']));
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_legacy_numbered_placeholders_untouched(): void
    {
        $this->assertSame('a {1} b', $this->f()->render('{n} {1} b', ['n' => 'a']));
    }

    public function test_nickname_codes_links_and_newlines_stripped(): void
    {
        $this->assertSame('Boblink', $this->f()->sanitise("\$f00Bob\$l[http://x]link"));
        $this->assertSame('ab', $this->f()->sanitise("a\r\nb"));
        $this->assertSame('5$$', $this->f()->sanitise('5$$'));
        $this->assertSame('x', $this->f()->render('{n}', ['n' => '$o$wx']));
    }

    public function test_raw_placeholders_keep_their_codes(): void
    {
        $this->assertSame('$f00 x', $this->f()->render('{pre} {n}', ['pre' => '$f00', 'n' => '$o$wx'], ['pre']));
    }
}
