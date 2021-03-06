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

namespace ProophTest\Micro\TestAsset;

use Iterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AggregateDefinition;

final class SingleStreamTestAggregateDefinition2 implements AggregateDefinition
{
    public function identifierName(): string
    {
        return 'id';
    }

    public function aggregateType(): string
    {
        return 'test';
    }

    public function versionName(): string
    {
        return 'version';
    }

    public function extractAggregateId(Message $message): string
    {
        return 'some_id';
    }

    public function extractAggregateVersion(Message $message): int
    {
        return 1;
    }

    public function streamName(): StreamName
    {
        return new StreamName('foo');
    }

    public function metadataEnricher(string $aggregateId, int $aggregateVersion, Message $causation = null): ?MetadataEnricher
    {
        return null;
    }

    public function metadataMatcher(string $aggregateId, int $aggregateVersion): ?MetadataMatcher
    {
        return null;
    }

    public function reconstituteState($state, Iterator $events)
    {
        return $state;
    }

    public function apply($state, Message ...$events)
    {
        return [];
    }

    public function hasOneStreamPerAggregate(): bool
    {
        return false;
    }

    public function stateType(): string
    {
        return TestState::class;
    }
}
