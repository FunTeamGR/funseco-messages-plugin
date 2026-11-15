<?php
namespace Funseco\Messages;

use Funseco\Core\Models\Player;

final class Text
{
    public static function get(string $key): string
    {
        return format_colors((string) config("messages.messages.$key", ''));
    }

    public static function tell(Player $player, string $message): void
    {
        \Server::query()->ChatSendServerMessageToLogin($message, $player->login);
    }

    public static function broadcast(string $message): void
    {
        \Server::query()->ChatSendServerMessage($message);
    }

    /**
     * Remove all TM formatting: colours, styles and $l/$h/$p links (link text kept,
     * "[url]" dropped); "$$" stays a literal dollar.
     */
    public static function strip(string $text): string
    {
        $text = str_replace('$$', "\0", $text);
        $text = preg_replace('/\$(?:[lhp]\[[^\]]*\]|[0-9a-f]{1,3}|.)?/isu', '', $text) ?? $text;

        return str_replace("\0", '$', $text);
    }

    /** Nickname without wide ($w) codes, as shown in logs. */
    public static function narrow(string $nickname): string
    {
        return str_ireplace('$w', '', $nickname);
    }
}
