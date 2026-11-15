<?php
namespace Funseco\Messages\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Core\SharedKernel\UI\Window;
use Funseco\Messages\ChatLog;
use Funseco\Messages\Text;

class ChatLogCommand implements Command, Described
{
    use DeclaresCommand;

    protected string $name = 'chatlog';
    protected string $description = 'Displays log of recent chat messages';

    public function handle(Player $player, array $args): void
    {
        $lines = app(ChatLog::class)->lines();
        if (!$lines) {
            Text::tell($player, Text::get('no_chat'));
            return;
        }

        Window::show($player, 'Recent chat history:', $lines, [1.2], ['Icons64x64_1', 'Outbox'],
            perPage: max(1, (int) config('messages.chat_log.per_page')));
    }
}
