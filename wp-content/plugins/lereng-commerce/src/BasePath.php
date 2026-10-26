<?php

namespace Lereng;

/**
 * Makes the store work when WordPress doesn't live at the domain root, as on
 * playground.wordpress.net (/scope:…/) or in a subfolder install:
 *  - scripts read the REST and home URLs from window.lerengBase
 *  - root-relative links in rendered pages ("/product-category/coffee/") get the base path
 * On a normal install at the root this does nothing.
 */
final class BasePath
{
    public function register(): void
    {
        add_action('wp_enqueue_scripts', static function (): void {
            wp_register_script('lereng-base', false, [], LERENG_VERSION, false);
            wp_enqueue_script('lereng-base');
            wp_add_inline_script('lereng-base', 'window.lerengBase=' . wp_json_encode(['rest' => esc_url_raw(rest_url()), 'home' => esc_url_raw(home_url('/')), 'lang' => I18n::current(), 'i18n' => (object) I18n::jsStrings()]) . ';');
        }, 1);
        $base = self::base();
        if ($base !== '') {
            add_action('template_redirect', static function () use ($base): void {
                ob_start(static fn(string $html): string => self::rewrite($html, $base));
            }, 0);
        }
    }

    public static function base(): string
    {
        return rtrim((string) wp_parse_url(home_url(), PHP_URL_PATH), '/');
    }

    /** Prefixes href, src and action values that start with a single slash. Pure, tested. */
    public static function rewrite(string $html, string $base): string
    {
        if ($base === '') {
            return $html;
        }
        $quoted = preg_quote(ltrim($base, '/'), '#');
        return (string) preg_replace("#(\\s(?:href|src|action)=[\"'])/(?!/|{$quoted}/)#", '$1' . $base . '/', $html);
    }
}
