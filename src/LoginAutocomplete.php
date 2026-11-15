<?php
namespace Funseco\Messages;

use Funseco\Core\Models\Player;
use Funseco\Core\Services\Cache\CurrentPlayers;
use Funseco\Core\Services\Cache\CurrentSpectators;
use Funseco\Core\Services\Cache\ServerInfo;
use Funseco\Core\SharedKernel\ChatArgumentExpander;

/**
 * In every typed chat line (commands and plain chat; plain lines need manual chat routing): "abc%" → first online login starting with "abc" ("server%" → the
 * server login), then "%login" → that player's nickname ("%<server login>" → server name).
 * Lowercase logins only; unmatched text stays as typed.
 */
class LoginAutocomplete implements ChatArgumentExpander
{
    private const LOGIN = '[a-z0-9._-]+';

    public function expand(Player $player, string $word): string
    {
        if (!config('messages.autocomplete.enabled') || !str_contains($word, '%')) {
            return $word;
        }

        $online = $this->online();
        $server = (string) config('funseco.master_server.login');

        $word = preg_replace_callback('/('.self::LOGIN.')%/', function ($m) use ($online, $server) {
            if ($m[1] === 'server') {
                return $server !== '' ? $server : $m[0];
            }
            foreach (array_keys($online) as $login) {
                if (str_starts_with((string) $login, $m[1])) {
                    return $login;
                }
            }

            return $m[0];
        }, $word);

        return preg_replace_callback('/%('.self::LOGIN.')/', function ($m) use ($online, $server) {
            if ($server !== '' && $m[1] === $server) {
                return (ServerInfo::get()['server_name'] ?? '').'$z$g$s';
            }

            return isset($online[$m[1]]) ? $online[$m[1]]->nickname.'$z$g$s' : $m[0];
        }, $word);
    }

    /** @return array<string, Player> players then spectators, server login excluded */
    private function online(): array
    {
        $online = CurrentPlayers::all()->all() + CurrentSpectators::all()->all();
        unset($online[(string) config('funseco.master_server.login')]);

        return $online;
    }
}
