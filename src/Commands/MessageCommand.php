<?php
namespace Funseco\Messages\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Messages\InfoMessages;

/** Broadcasts one random info message now (open to everyone, no cooldown). */
class MessageCommand implements Command, Described
{
    use DeclaresCommand;

    protected string $name = 'message';
    protected string $description = 'Shows a random info message';

    public function handle(Player $player, array $args): void
    {
        app(InfoMessages::class)->random(atEndOfTrack: false);
    }
}
