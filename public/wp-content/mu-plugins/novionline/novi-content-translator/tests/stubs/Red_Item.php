<?php

/**
 * Minimal Redirection Red_Item stub for NCT unit tests.
 * Mirrors production behaviour: get_for_url() always returns enabled regex candidates.
 */
class Red_Item
{
    /** @var string */
    public $url = '';

    /** @var string */
    public $action_data = '';

    /** @var int */
    public $regex = 0;

    /** @var string */
    public $match_url = '';

    /** @var string */
    public $action_type = 'url';

    /**
     * @param array{url?: string, action_data?: string, regex?: int|bool, match_url?: string, action_type?: string} $data
     */
    public function __construct(array $data = [])
    {
        $this->url = (string) ($data['url'] ?? '');
        $this->action_data = (string) ($data['action_data'] ?? '');
        $this->regex = !empty($data['regex']) ? 1 : 0;
        $this->match_url = (string) ($data['match_url'] ?? ($this->regex ? 'regex' : $this->url));
        $this->action_type = (string) ($data['action_type'] ?? 'url');
    }

    /**
     * @return list<Red_Item>
     */
    public static function get_for_url(string $url): array
    {
        $items = $GLOBALS['__nct_red_item_candidates'] ?? null;
        if (is_callable($items)) {
            $out = $items($url);
            return is_array($out) ? $out : [];
        }
        if (is_array($items)) {
            return $items;
        }

        // default: always surface a false-positive team regex (prod shape)
        return [
            new self([
                'url' => '^/team/(.+)',
                'action_data' => '/over-ons/team/',
                'regex' => 1,
                'match_url' => 'regex',
            ]),
        ];
    }

    public function get_url(): string
    {
        return $this->url;
    }

    public function get_action_data(): string
    {
        return $this->action_data;
    }

    public function get_action_type(): string
    {
        return $this->action_type;
    }

    public function is_regex(): bool
    {
        return (bool) $this->regex;
    }

    public function get_match_url(): string
    {
        return $this->match_url;
    }
}
