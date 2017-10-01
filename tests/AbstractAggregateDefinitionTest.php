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

namespace ProophTest\Micro;

use PHPUnit\Framework\TestCase;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\Metadata\FieldType;
use Prooph\EventStore\Metadata\MetadataEnricher;
use Prooph\EventStore\Metadata\MetadataMatcher;
use Prooph\EventStore\Metadata\Operator;
use Prooph\EventStore\StreamName;
use Prooph\Micro\AbstractAggregateDefinition;

class AbstractAggregateDefinitionTest extends TestCase
{
    /**
     * @test
     */
    public function it_returns_identifier_and_version_name(): void
    {
        $this->assertEquals('id', $this->createDefinition()->identifierName());
        $this->assertEquals('version', $this->createDefinition()->versionName());
    }

    /**
     * @test
     */
    public function it_extracts_aggregate_id(): void
    {
        $message = $this->prophesize(Message::class);
        $message->payload()->willReturn(['id' => 'some_id'])->shouldBeCalled();

        $this->assertEquals('some_id', $this->createDefinition()->extractAggregateId($message->reveal()));
    }

    /**
     * @test
     */
    public function it_throws_exception_when_no_id_property_found_during_extraction(): void
    {
        $this->expectException(\RuntimeException::class);

        $message = $this->prophesize(Message::class);
        $message->payload()->willReturn([])->shouldBeCalled();

        $this->createDefinition()->extractAggregateId($message->reveal());
    }

    /**
     * @test
     */
    public function it_extracts_aggregate_version(): void
    {
        $message = $this->prophesize(Message::class);
        $message->payload()->willReturn(['version' => 5])->shouldBeCalled();

        $this->assertEquals(5, $this->createDefinition()->extractAggregateVersion($message->reveal()));
    }

    /**
     * @test
     */
    public function it_throws_exception_when_no_version_property_found_during_extraction(): void
    {
        $this->expectException(\RuntimeException::class);

        $message = $this->prophesize(Message::class);
        $message->payload()->willReturn([])->shouldBeCalled();

        $this->createDefinition()->extractAggregateVersion($message->reveal());
    }

    /**
     * @test
     */
    public function it_returns_metadata_matcher(): void
    {
        $metadataMatcher = $this->createDefinition()->metadataMatcher('some_id', 5);

        $this->assertInstanceOf(MetadataMatcher::class, $metadataMatcher);

        $this->assertEquals(
            [
                [
                    'field' => '_aggregate_id',
                    'operator' => Operator::EQUALS(),
                    'value' => 'some_id',
                    'fieldType' => FieldType::METADATA(),
                ],
                [
                    'field' => '_aggregate_type',
                    'operator' => Operator::EQUALS(),
                    'value' => 'foo',
                    'fieldType' => FieldType::METADATA(),
                ],
                [
                    'field' => '_aggregate_version',
                    'operator' => Operator::GREATER_THAN_EQUALS(),
                    'value' => 5,
                    'fieldType' => FieldType::METADATA(),
                ],
            ],
            $metadataMatcher->data()
        );
    }

    /**
     * @test
     */
    public function it_returns_metadata_enricher(): void
    {
        $enricher = $this->createDefinition()->metadataEnricher('some_id', 42);

        $this->assertInstanceOf(MetadataEnricher::class, $enricher);

        $enrichedMessage = $this->prophesize(Message::class);
        $enrichedMessage->withAddedMetadata('_aggregate_type', 'foo')->willReturn($enrichedMessage)->shouldBeCalled();
        $enrichedMessage->withAddedMetadata('_aggregate_version', 42)->willReturn($enrichedMessage)->shouldBeCalled();
        $enrichedMessage = $enrichedMessage->reveal();

        $message = $this->prophesize(Message::class);
        $message->withAddedMetadata('_aggregate_id', 'some_id')->willReturn($enrichedMessage)->shouldBeCalled();

        $result = $enricher->enrich($message->reveal());

        $this->assertSame($enrichedMessage, $result);
    }

    /**
     * @test
     */
    public function it_reconstitutes_state(): void
    {
        $message = $this->prophesize(Message::class);

        $state = $this->createDefinition()->reconstituteState([], new \ArrayIterator([$message->reveal()]));

        $this->assertArrayHasKey('count', $state);
        $this->assertEquals(1, $state['count']);
    }

    /**
     * @test
     */
    public function it_has_not_one_stream_per_aggregate_as_default(): void
    {
        $this->assertFalse($this->createDefinition()->hasOneStreamPerAggregate());
    }

    public function createDefinition(): AbstractAggregateDefinition
    {
        return new class() extends AbstractAggregateDefinition {
            public function streamName(): StreamName
            {
                return new StreamName('foo');
            }

            public function aggregateType(): string
            {
                return 'foo';
            }

            public function apply($state, Message ...$events): array
            {
                if (! isset($state['count'])) {
                    $state['count'] = 0;
                }

                foreach ($events as $event) {
                    ++$state['count'];
                }

                return $state;
            }
        };
    }
}
