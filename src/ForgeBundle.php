<?php

namespace Base\Forge;

use Base\Bundle\AbstractBaseBundle;
use Base\Traits\SingletonTrait;

/**
 * The studio's forge on top of base-bundle-market: software and its releases
 * (built from git tags through git/git-bundle), free and licensed downloads,
 * a private Composer repository, client projects with a shared board and
 * logged time, prepaid support hours and quotes.
 */
class ForgeBundle extends AbstractBaseBundle
{
    // Own singleton storage rather than sharing AbstractBaseBundle's - see
    // that class's constructor for why every concrete bundle needs this.
    use SingletonTrait;

    // The trait's protected no-op __construct() would hide the inherited
    // one; redeclared public, as MarketBundle and AdminBundle do.
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Modern bundle layout: the class lives in src/, the bundle root is the
     * package root - so TwigBundle picks up ./templates as @Forge and
     * Doctrine's auto_mapping ./src/Entity.
     */
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
