<?php

declare(strict_types=1);

namespace WPT\WordPress;

use WPT\Token\TokenIssuer;
use WP_Post;

class PreviewLink
{
    private TokenIssuer $issuer;
    private Settings $settings;

    public function __construct(TokenIssuer $issuer, Settings $settings)
    {
        $this->issuer   = $issuer;
        $this->settings = $settings;
    }

    public function register(): void
    {
        add_filter('preview_post_link', [$this, 'replace_preview_link'], 10, 2);
        add_filter('post_row_actions',  [$this, 'add_new_tab_to_preview_action'], 10, 2);
        add_filter('page_row_actions',  [$this, 'add_new_tab_to_preview_action'], 10, 2);
    }

    /**
     * Replace the preview URL with the stored token URL if one exists and is valid.
     * Token generation is now handled explicitly via the Gutenberg sidebar "Generate token" button.
     */
    public function replace_preview_link(string $preview_link, WP_Post $post): string
    {
        $frontend_url = $this->settings->get_frontend_url();
        if ($frontend_url === '') {
            return $preview_link;
        }

        $data = $this->issuer->get_by_post($post->ID);

        if (!$data || time() > $data['expires_at']) {
            return $preview_link;
        }

        return add_query_arg('token', $data['raw'], $frontend_url);
    }

    /**
     * Add target="_blank" to the "Preview" link in the post list row actions.
     *
     * @param array<string, string> $actions
     * @return array<string, string>
     */
    public function add_new_tab_to_preview_action(array $actions, WP_Post $post): array
    {
        if (!isset($actions['view'])) {
            return $actions;
        }

        $actions['view'] = preg_replace_callback(
            '/<a\s[^>]+>/',
            static function (array $matches): string {
                $tag = $matches[0];
                $tag = str_replace('rel="bookmark"', 'rel="noopener noreferrer" target="_blank"', $tag);
                if (strpos($tag, 'target=') === false) {
                    $tag = str_replace('<a ', '<a target="_blank" rel="noopener noreferrer" ', $tag);
                }
                return $tag;
            },
            $actions['view']
        ) ?? $actions['view'];

        return $actions;
    }
}
