<?php

namespace Funseco\Messages\Tests\Feature;

use Funseco\Core\SharedKernel\Messages\Message;
use Funseco\Core\SharedKernel\Messages\MessageRegistry;
use Funseco\Core\SharedKernel\Messages\Recipients;
use Funseco\Core\SharedKernel\Enums\MessageType;
use PHPUnit\Framework\TestCase;

class MessageRegistryTest extends TestCase
{
    public function test_declare_binds_key_and_find_returns_it(): void
    {
        $r = new MessageRegistry();
        $r->declare('core', [Message::make('welcome', 'Welcome', 'Hi {server}', ['server' => 'Server name'])]);
        $m = $r->find('core.welcome');
        $this->assertSame('core.welcome', $m->key());
        $this->assertSame('core', $m->plugin);
        $this->assertSame(MessageType::Info, $m->style);
        $this->assertSame(Recipients::Everyone, $m->recipients);
        $this->assertNull($r->find('core.nope'));
    }

    public function test_duplicate_key_throws(): void
    {
        $r = new MessageRegistry();
        $r->declare('core', [Message::make('a', 'A', 'x')]);
        $this->expectException(\LogicException::class);
        $r->declare('core', [Message::make('a', 'A', 'y')]);
    }
}
