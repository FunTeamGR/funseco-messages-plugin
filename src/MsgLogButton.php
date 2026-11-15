<?php
namespace Funseco\Messages;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Chat\Facades\Chat;
use Funseco\Core\Services\ManiaLink\Facades\ManiaLink;

/** Permanent bottom-left button that runs /msglog for the clicking player. */
class MsgLogButton
{
    public function show(Player $player): void
    {
        if (!config('messages.msglog_button.enabled')) {
            return;
        }

        $pos = config('manialinks.msglog_button.pos_x', -63.9).' '.config('manialinks.msglog_button.pos_y', -33.5);
        $id = ManiaLink::getAction('msglog_button');
        ManiaLink::displayLogin($player, '<manialink id="'.$id.'"><frame posn="'.$pos.' 0">'
            .'<quad sizen="1.65 1.65" style="Icons64x64_1" substyle="ArrowUp" action="'.$id.'"/>'
            .'</frame></manialink>', 0, false);
    }

    /** Click handler. */
    public function handle(Player $player, string $action): void
    {
        Chat::run($player, 'msglog');
    }
}
