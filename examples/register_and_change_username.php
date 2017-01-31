<?php
/**
 * This file is part of the prooph/micro.
 * (c) 2017-2017 prooph software GmbH <contact@prooph.de>
 * (c) 2017-2017 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\MicroExample\Script;

use Prooph\Common\Messaging\Message;
use Prooph\Micro\Kernel;
use Prooph\MicroExample\Infrastructure\UserAggregateDefinition;
use Prooph\MicroExample\Model\Command\ChangeUserName;
use Prooph\MicroExample\Model\Command\InvalidCommand;
use Prooph\MicroExample\Model\Command\RegisterUser;
use Prooph\MicroExample\Model\Command\UnknownCommand;
use Prooph\MicroExample\Model\User;

$start = microtime(true);

$autoloader = require __DIR__ . '/../vendor/autoload.php';
$autoloader->addPsr4('Prooph\\MicroExample\\', __DIR__);
require 'Model/User.php';

//We could also use a container here, if dependencies grow
$factories = include 'Infrastructure/factories.php';

$commandMap = [
    RegisterUser::class => [
        'handler' => function (callable $stateResolver, Message $message) use (&$factories): array {
            return User\registerUser($stateResolver, $message, $factories['emailGuard']());
        },
        'definition' => UserAggregateDefinition::class,
    ],
    ChangeUserName::class => [
        'handler' => User\changeUserName,
        'definition' => UserAggregateDefinition::class,
    ],
];

$dispatch = Kernel\buildCommandDispatcher(
    $commandMap,
    $factories['eventStore'],
    $factories['snapshotStore']
);

$command = new RegisterUser(['id' => '1', 'name' => 'Alex', 'email' => 'member@getprooph.org']);

$events = $dispatch($command);

echo "User was registered, emitted event payload: \n";
echo json_encode($events[0]->payload()) . "\n\n";

$events = $dispatch(new ChangeUserName(['id' => '1', 'name' => 'Sascha']));

echo "Username changed, emitted event payload: \n";
echo json_encode($events[0]->payload()) . "\n\n";

// should return a TypeError
$throwable = $dispatch(new InvalidCommand());

echo get_class($throwable) . "\n";
echo $throwable->getMessage() . "\n\n";

$throwable = $dispatch(new UnknownCommand());

// should return a RuntimeException
echo get_class($throwable) . "\n";
echo $throwable->getMessage() . "\n\n";

$time = microtime(true) - $start;

echo $time . "secs runtime\n\n";
