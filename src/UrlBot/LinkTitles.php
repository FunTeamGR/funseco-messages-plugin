<?php
namespace Funseco\Messages\UrlBot;

use Psr\Http\Message\ResponseInterface;
use React\Promise\PromiseInterface;
use Funseco\Core\Services\Http\AsyncHttpClient;
use Funseco\Messages\Text;

/**
 * Looks up the title of a chat link: YouTube Data API for YouTube videos (when a key is
 * configured), otherwise the page's <title>. Resolves to '' when there is none.
 */
class LinkTitles
{
    private const YOUTUBE_API = 'https://www.googleapis.com/youtube/v3/videos';

    public function __construct(private AsyncHttpClient $http)
    {
    }

    /** @return PromiseInterface<string> */
    public function pageTitle(string $url): PromiseInterface
    {
        return $this->http
            ->getUntrusted($url, ['User-Agent' => (string) config('messages.url_bot.user_agent')],
                (float) config('messages.url_bot.timeout'), (int) config('messages.url_bot.max_bytes'))
            ->then(
                fn (ResponseInterface $response) => preg_match('/<title[^>]*>(.*?)<\/title>/i', (string) $response->getBody(), $m)
                    ? self::clean($m[1]) : '',
                fn () => '',
            );
    }

    /** @return PromiseInterface<string> */
    public function youtubeTitle(string $videoId, string $url): PromiseInterface
    {
        $key = config('messages.url_bot.youtube_api_key');
        if (!$key || $videoId === '') {
            return $this->pageTitle($url);
        }

        $api = self::YOUTUBE_API.'?'.http_build_query(['id' => $videoId, 'part' => 'snippet', 'key' => $key]);

        return $this->http
            ->getUntrusted($api, [], (float) config('messages.url_bot.timeout'), (int) config('messages.url_bot.max_bytes'))
            ->then(
                fn (ResponseInterface $response) => self::clean((string) (json_decode((string) $response->getBody(), true)['items'][0]['snippet']['title'] ?? '')),
                fn () => '',
            )
            ->then(fn (string $title) => $title !== '' ? $title : $this->pageTitle($url));
    }

    /** Decode entities, drop emojis and TM formatting, escape "$", cap length. */
    public static function clean(string $title): string
    {
        $title = html_entity_decode(clean_utf8($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $title = preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{FE00}-\x{FE0F}\x{200D}\x{E0020}-\x{E007F}]/u', '', $title) ?? '';
        $title = preg_replace('/\s+/u', ' ', Text::strip($title)) ?? '';
        $title = trim(mb_substr(trim($title), 0, max(1, (int) config('messages.url_bot.max_length'))));

        return str_replace('$', '$$', $title);
    }

}
