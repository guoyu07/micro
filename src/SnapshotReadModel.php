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

namespace Prooph\Micro;

use BadMethodCallException;
use DateTimeImmutable;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Projection\ReadModel;
use Prooph\SnapshotStore\Snapshot;
use Prooph\SnapshotStore\SnapshotStore;
use RuntimeException;

final class SnapshotReadModel implements ReadModel
{
    /**
     * @var SnapshotStore
     */
    private $snapshotStore;

    /**
     * @var AggregateDefinition
     */
    private $aggregateDefinition;

    /**
     * @var array
     */
    private $cache = [];

    public function __construct(
        SnapshotStore $snapshotStore,
        AggregateDefinition $aggregateDefiniton
    ) {
        $this->snapshotStore = $snapshotStore;
        $this->aggregateDefinition = $aggregateDefiniton;
    }

    public function stack(string $operation, ...$events): void
    {
        foreach ($events as $event) {
            if (! $event instanceof Message) {
                throw new RuntimeException(get_class($this) . ' can only handle events of type ' . Message::class);
            }

            $aggregateId = $this->aggregateDefinition->extractAggregateId($event);

            if (! isset($this->cache[$aggregateId])) {
                $snapshot = $this->snapshotStore->get(
                    $this->aggregateDefinition->aggregateType(),
                    $aggregateId
                );

                if (! $snapshot) {
                    $state = [];
                } else {
                    $state = $snapshot->aggregateRoot();
                }
            } else {
                $state = $this->cache[$aggregateId];
            }

            $this->cache[$aggregateId] = $this->aggregateDefinition->apply($state, $event);
        }
    }

    public function persist(): void
    {
        $snapshots = [];

        foreach ($this->cache as $aggregateId => $state) {
            $snapshots[] = new Snapshot(
                $this->aggregateDefinition->aggregateType(),
                $aggregateId,
                $state,
                $state[$this->aggregateDefinition->versionName()],
                new DateTimeImmutable('now', new \DateTimeZone('UTC'))
            );
        }

        $this->snapshotStore->save(...$snapshots);

        $this->cache = [];
    }

    public function init(): void
    {
        throw new BadMethodCallException('Initializing a snapshot read model is not supported');
    }

    public function isInitialized(): bool
    {
        return true;
    }

    public function reset(): void
    {
        throw new BadMethodCallException('Resetting a snapshot read model is not supported');
    }

    public function delete(): void
    {
        throw new BadMethodCallException('Deleting a snapshot read model is not supported');
    }
}
