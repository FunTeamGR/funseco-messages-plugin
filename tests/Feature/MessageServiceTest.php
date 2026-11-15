<?php

namespace Funseco\Messages\Tests\Feature;

use Funseco\Core\Services\Messages\MessageService;
use Funseco\Core\SharedKernel\Events\MessageChanged;
use Funseco\Core\SharedKernel\Messages\InvalidMessageValue;
use Funseco\Core\SharedKernel\Messages\Message;
use Funseco\Core\SharedKernel\Messages\MessageRegistry;
use Funseco\Core\SharedKernel\Messages\Recipients;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Funseco\Messages\Tests\TestCase;

class MessageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(MessageRegistry::class)->declare('t', [Message::make('m', 'M', 'Hello {x}', ['x' => 'X'],
            recipients: Recipients::Player, allowedRecipients: [Recipients::Player, Recipients::Staff], delay: 2)]);
    }

    private function svc(): MessageService
    {
        return app(MessageService::class);
    }

    public function test_effective_defaults_without_row(): void
    {
        $e = $this->svc()->effective('t.m');
        $this->assertSame(['Hello {x}', true, Recipients::Player, 2], [$e->text, $e->enabled, $e->recipients, $e->delay]);
    }

    public function test_row_overrides_default(): void
    {
        $this->svc()->set('t.m', 'text', 'Yo');
        $this->svc()->set('t.m', 'enabled', false);
        $this->svc()->set('t.m', 'recipients', 'staff');
        $this->svc()->set('t.m', 'delay', 5);
        $e = $this->svc()->effective('t.m');
        $this->assertSame(['Yo', false, Recipients::Staff, 5], [$e->text, $e->enabled, $e->recipients, $e->delay]);
    }

    public function test_delay_9999_rejected(): void
    {
        $this->expectException(InvalidMessageValue::class);
        $this->svc()->set('t.m', 'delay', 9999);
    }

    public function test_delay_abc_rejected(): void
    {
        $this->expectException(InvalidMessageValue::class);
        $this->svc()->set('t.m', 'delay', 'abc');
    }

    public function test_recipient_not_allowed_rejected(): void
    {
        $this->expectException(InvalidMessageValue::class);
        $this->svc()->set('t.m', 'recipients', 'everyone');
    }

    public function test_invalid_stored_row_ignored_with_warning(): void
    {
        Log::spy();
        DB::table('message_overrides')->insert(['key' => 't.m', 'delay' => 'abc', 'text' => 'Kept']);
        $this->svc()->overlay();
        $e = $this->svc()->effective('t.m');
        $this->assertSame(2, $e->delay);
        $this->assertSame('Kept', $e->text);
        Log::shouldHaveReceived('warning')->atLeast()->once();
    }

    public function test_missing_table_yields_defaults(): void
    {
        Schema::drop('message_overrides');
        $this->svc()->overlay();
        $this->assertSame('Hello {x}', $this->svc()->effective('t.m')->text);
    }

    public function test_set_fires_message_changed_and_reset_restores(): void
    {
        Event::fake([MessageChanged::class]);
        $this->svc()->set('t.m', 'text', 'Yo');
        Event::assertDispatched(MessageChanged::class, fn ($e) => $e->key === 't.m');
        $this->svc()->reset('t.m', 'text');
        $this->assertSame('Hello {x}', $this->svc()->effective('t.m')->text);
    }

    public function test_unknown_key_and_overlong_text_rejected(): void
    {
        $this->expectException(InvalidMessageValue::class);
        $this->svc()->set('t.m', 'text', str_repeat('a', 401));
    }

    public function test_a_failed_read_is_not_cached(): void
    {
        $this->svc()->set('t.m', 'enabled', false);
        $store = new \Funseco\Core\Services\Messages\MessageStore();
        Schema::rename('message_overrides', 'message_overrides_away');
        $this->assertSame([], $store->rows());
        Schema::rename('message_overrides_away', 'message_overrides');
        $this->assertArrayHasKey('t.m', $store->rows(), 'the next read retries');
    }

    public function test_numbered_placeholders_are_rejected_in_text(): void
    {
        $this->expectException(InvalidMessageValue::class);
        $this->svc()->set('t.m', 'text', 'Hello {1}');
    }

    public function test_a_style_body_token_must_be_a_known_colour_token(): void
    {
        $styles = new \Funseco\Core\Services\Messages\StyleStore();
        $styles->set(\Funseco\Core\SharedKernel\Enums\MessageType::Info, 'body_token', 'record');
        $this->expectException(\InvalidArgumentException::class);
        $styles->set(\Funseco\Core\SharedKernel\Enums\MessageType::Info, 'body_token', 'nonsense');
    }

    public function test_a_failed_style_read_is_not_cached(): void
    {
        $styles = new \Funseco\Core\Services\Messages\StyleStore();
        $info = \Funseco\Core\SharedKernel\Enums\MessageType::Info;
        $styles->set($info, 'prefix', 'X>');
        $fresh = new \Funseco\Core\Services\Messages\StyleStore();
        Schema::rename('message_styles', 'message_styles_away');
        $this->assertNotSame('X>', $fresh->get($info)['prefix']);
        Schema::rename('message_styles_away', 'message_styles');
        $this->assertSame('X>', $fresh->get($info)['prefix'], 'the next read retries');
    }
}
