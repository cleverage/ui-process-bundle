<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Tests\Controller\Admin;

use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Context\RequestStackContext;
use Symfony\Component\Asset\Packages;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * The logo URL is built by the Asset component: base path of the application, assets base URLs.
 */
#[CoversClass(ProcessDashboardController::class)]
class ProcessDashboardControllerTest extends TestCase
{
    /**
     * @return iterable<string, array{Packages, string, string}>
     */
    public static function provideLogos(): iterable
    {
        $requestStack = new RequestStack();
        $request = Request::create('http://localhost/app/process', server: ['SCRIPT_NAME' => '/app/index.php', 'SCRIPT_FILENAME' => '/var/www/public/index.php']);
        $requestStack->push($request);
        $pathPackage = new Packages(new PathPackage('/', new EmptyVersionStrategy(), new RequestStackContext($requestStack)));

        yield 'default' => [$pathPackage, 'bundles/cleverageuiprocess/logo.jpg', '<img src="/app/bundles/cleverageuiprocess/logo.jpg" />'];
        yield 'absolute URL' => [$pathPackage, 'https://cdn.example.com/logo.png', '<img src="https://cdn.example.com/logo.png" />'];
        yield 'assets base URL' => [
            new Packages(new UrlPackage('https://cdn.example.com/', new EmptyVersionStrategy())),
            'bundles/cleverageuiprocess/logo.jpg',
            '<img src="https://cdn.example.com/bundles/cleverageuiprocess/logo.jpg" />',
        ];
        yield 'escaped' => [$pathPackage, 'logo.png?a=1&b="2"', '<img src="/app/logo.png?a=1&amp;b=&quot;2&quot;" />'];
    }

    #[DataProvider('provideLogos')]
    public function testLogoUrl(Packages $packages, string $logoPath, string $expectedTitle): void
    {
        $controller = new ProcessDashboardController(new LocaleSwitcher('en', []), $packages, $logoPath);

        self::assertSame($expectedTitle, $controller->configureDashboard()->getAsDto()->getTitle());
    }
}
