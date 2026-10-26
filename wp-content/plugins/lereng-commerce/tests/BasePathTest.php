<?php

namespace Lereng\Tests;

use Lereng\BasePath;
use PHPUnit\Framework\TestCase;

final class BasePathTest extends TestCase
{
    public function testRootRelativeLinksGetTheBase(): void
    {
        $html = '<a href="/product-category/coffee/">x</a><img src="/wp-content/a.jpg"><form action="/checkout/">';
        $this->assertSame('<a href="/scope:0.5/product-category/coffee/">x</a><img src="/scope:0.5/wp-content/a.jpg"><form action="/scope:0.5/checkout/">', BasePath::rewrite($html, '/scope:0.5'));
    }

    public function testLeavesAbsoluteProtocolRelativeAndAlreadyPrefixedLinks(): void
    {
        $html = '<a href="https://x.test/a">a</a><script src="//cdn.test/x.js"></script><a href="/scope:0.5/shop/">s</a><a href="#top">t</a>';
        $this->assertSame($html, BasePath::rewrite($html, '/scope:0.5'));
    }

    public function testNoBaseNoChange(): void
    {
        $this->assertSame('<a href="/x">', BasePath::rewrite('<a href="/x">', ''));
    }
}
