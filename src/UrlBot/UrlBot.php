<?php
namespace Funseco\Messages\UrlBot;

use React\Promise\PromiseInterface;
use Funseco\Core\Models\Player;
use Funseco\Messages\Text;

use function React\Promise\resolve;

/**
 * Posts the title of each link ($l…) a player writes in chat, after their line:
 * "[URL Bot] title" or "[YouTube Bot] title". Every typed line counts, /pm included: that
 * bot line goes privately (prefixed "-pm-") to the addressee and the sender.
 */
class UrlBot
{
    public function __construct(private LinkTitles $titles)
    {
    }

    public function chatted(Player $player, string $text): void
    {
        if (!config('messages.url_bot.enabled') || stripos($text, '$l') === false
            || $player->login === config('funseco.master_server.login')) {
            return;
        }

        $words = explode(' ', $text);
        $pm = $words[0] === '/pm' ? array_values(array_unique(array_filter([(string) ($words[1] ?? ''), $player->login]))) : null;

        // One link after another, so the bot lines keep the order of the links.
        $chain = resolve(null);
        foreach ($words as $word) {
            if (($link = self::link($word)) === null) {
                continue;
            }
            $chain = $chain->then(fn () => $this->title($link))->then(function (array $result) use ($pm) {
                [$prefix, $title] = $result;
                if ($title === '') {
                    return;
                }
                $line = config("messages.url_bot.prefix.$prefix").$title;
                if ($pm === null) {
                    Text::broadcast($line);

                    return;
                }
                foreach ($pm as $login) {
                    \Server::query()->ChatSendServerMessageToLogin('$i$f00-pm-'.$line, $login);
                }
            }, fn () => null);
        }
    }

    /** URL of a chat word that starts with "$l" ("$lexample.com", "$l[http://x]text"), else null. */
    public static function link(string $word): ?string
    {
        if (!str_starts_with($word, '$l')) {
            return null;
        }
        if (preg_match('/^\$l\[([^\]]+)\]/', $word, $m)) {
            return trim($m[1]);
        }
        $url = trim(str_ireplace('$l', '', $word));

        return $url === '' ? null : $url;
    }

    /** @return PromiseInterface<array{0: string, 1: string}> [prefix key, title] */
    private function title(string $link): PromiseInterface
    {
        $hasScheme = (bool) preg_match('#^[a-z][a-z0-9+.-]*://#i', $link);
        $url = $hasScheme ? $link : 'http://'.$link;
        if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return resolve(['url', '']);
        }

        // Like the original: only a link with a scheme has a host, so "www.youtube.com/…" is a plain link.
        $host = $hasScheme ? preg_replace('/^www\./i', '', strtolower((string) parse_url($link, PHP_URL_HOST))) : '';
        if (in_array($host, ['youtube.com', 'youtu.be'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $id = is_string($query['v'] ?? null) ? $query['v'] : ltrim((string) parse_url($url, PHP_URL_PATH), '/');

            return $this->titles->youtubeTitle($id, $url)->then(fn (string $title) => ['youtube', $title]);
        }

        return $this->titles->pageTitle($url)->then(fn (string $title) => ['url', $title]);
    }
}
