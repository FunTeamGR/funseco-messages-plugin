<?php

namespace Funseco\Messages\Tests\Feature;

use Funseco\Core\Services\Messages\ChatColors;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Funseco\Messages\Tests\TestCase;

class ChatColorsTest extends TestCase
{
    use RefreshDatabase;

    private function colors(): ChatColors
    {
        return app(ChatColors::class);
    }

    public function test_order_theme_db_beats_theme_file_beats_base_db_beats_config(): void
    {
        $c = $this->colors();
        $this->assertSame(config('colors.message'), $c->resolve('message', 'classic'));
        $c->set('message', null, '$111');
        $this->assertSame('$111', $c->resolve('message', 'classic'));
        $this->assertSame('$fc9', $c->resolve('message', 'warm'), 'theme file beats base row');
        $c->set('message', 'warm', '$222');
        $this->assertSame('$222', $c->resolve('message', 'warm'));
        $this->assertSame('theme_db', $c->layers('message', 'warm')['source']);
    }

    public function test_theme_file_value_survives_base_edit(): void
    {
        $this->colors()->set('server', null, '$123');
        $this->assertSame('theme_file', $this->colors()->layers('server', 'warm')['source']);
        $this->assertSame('base_db', $this->colors()->layers('server', 'classic')['source']);
    }

    public function test_reset_returns_to_next_layer(): void
    {
        $c = $this->colors();
        $c->set('message', null, '$111');
        $c->set('message', 'warm', '$222');
        $c->reset('message', 'warm');
        $this->assertSame('$fc9', $c->resolve('message', 'warm'));
        $c->reset('message', null);
        $this->assertSame(config('colors.message'), $c->resolve('message', 'classic'));
        $this->assertSame('config', $c->layers('message', 'classic')['source']);
    }

    public function test_a_code_with_style_codes_like_the_default_error_colour_is_accepted(): void
    {
        $this->colors()->set('error', null, '$f00$i');
        $this->assertSame('$f00$i', $this->colors()->resolve('error', 'classic'));
    }

    public function test_invalid_code_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->colors()->set('message', null, 'bf5');
    }

    public function test_missing_table_falls_back_to_config(): void
    {
        Schema::drop('message_colors');
        $this->assertSame(config('colors.server'), $this->colors()->resolve('server', 'classic'));
    }

    public function test_a_failed_read_is_not_cached(): void
    {
        $this->colors()->set('message', null, '$abc');
        $fresh = new \Funseco\Core\Services\Messages\ChatColors();
        Schema::rename('message_colors', 'message_colors_away');
        $this->assertSame(config('colors.message'), $fresh->resolve('message', 'classic'));
        Schema::rename('message_colors_away', 'message_colors');
        $this->assertSame('$abc', $fresh->resolve('message', 'classic'), 'the next read retries');
    }
}
