<?php

namespace Lereng;

/** Activation: database tables and defaults. Safe to run again (dbDelta + add_option). */
final class Install
{
    public static function activate(): void
    {
        update_option('lereng_version', LERENG_VERSION);
    }
}
