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

/*
 * A process execution ended by a fatal error after some log records, the maximum execution time ("time" argument) or
 * the memory limit ("memory" argument) exceeded: executed in a PHP process of its own by
 * DoctrineProcessHandlerEntityManagerTest, with the container and the database of the test
 * (UI_PROCESS_BUNDLE_TESTS_RUN_DIR).
 */

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Monolog\Handler\HandlerInterface;
use Monolog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

require dirname(__DIR__, 3).'/vendor/autoload.php';

$kernel = new TestKernel('test', false);
$kernel->boot();
/** @var ContainerInterface $container */
$container = $kernel->getContainer()->get('test.service_container');
/** @var EventDispatcherInterface $eventDispatcher */
$eventDispatcher = $container->get('event_dispatcher');
$eventDispatcher->dispatch(new ProcessEvent('test.process'), ProcessEvent::EVENT_PROCESS_STARTED);
// The test application stops the log records with a "null" handler: the handlers of the bundle only
/** @var HandlerInterface $fileHandler */
$fileHandler = $container->get('cleverage_ui_process.monolog_handler.process');
/** @var HandlerInterface $databaseHandler */
$databaseHandler = $container->get('cleverage_ui_process.monolog_handler.doctrine_process');
$logger = new Logger('cleverage_process', [$fileHandler, $databaseHandler]);
$logger->info('before the warning');
$logger->warning('warning');
$logger->info('after the warning');

if ('time' === ($argv[1] ?? null)) {
    set_time_limit(1);
    $count = 0;
    while (hrtime(true) > 0) {
        ++$count;
    }
}
ini_set('memory_limit', (string) (memory_get_usage() + 8 * 1024 * 1024));
$data = [];
while (count($data) < \PHP_INT_MAX) {
    $data[] = str_repeat('x', 1024);
}
