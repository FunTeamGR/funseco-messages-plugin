<?php
namespace Funseco\Messages\Commands;

use Funseco\Core\Models\Player;
use Funseco\Core\SharedKernel\Commands\Command;
use Funseco\Core\SharedKernel\Commands\DeclaresCommand;
use Funseco\Core\SharedKernel\Commands\Described;
use Funseco\Core\SharedKernel\UI\Window;
use Funseco\Messages\MessageHistory;
use Funseco\Messages\Text;

class MsgLogCommand implements Command, Described
{
    use DeclaresCommand;

    protected string $name = 'msglog';
    protected string $description = 'Displays log of recent system messages';

    public function handle(Player $player, array $args): void
    {
        $history = app(MessageHistory::class)->all();
        if (!$history) {
            Text::tell($player, Text::get('no_messages'));
            return;
        }

        // the whole history on one page
        Window::show($player, 'Recent system message history:', array_map(fn ($line) => [$line], $history),
            [1.53], ['Icons64x64_1', 'NewMessage'], perPage: max(21, count($history)));
    }
}
