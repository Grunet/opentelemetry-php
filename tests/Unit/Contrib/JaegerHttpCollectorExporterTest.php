<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Unit\Contrib;

use Mockery;
use OpenTelemetry\Contrib\Jaeger\HttpCollectorExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\Tests\Unit\SDK\Util\SpanData;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @covers OpenTelemetry\Contrib\Jaeger\HttpCollectorExporter
 * @covers OpenTelemetry\Contrib\Jaeger\HttpSender
 * @covers OpenTelemetry\Contrib\Jaeger\ThriftHttpTransport
 * @covers OpenTelemetry\Contrib\Jaeger\ParsedEndpointUrl
 */
class JaegerHttpCollectorExporterTest extends TestCase
{
    use UsesHttpClientTrait;

    public function test_happy_path()
    {
        /**
         * @psalm-suppress PossiblyInvalidArgument
         */
        $exporter = new HttpCollectorExporter(
            'https://hostOfJaegerCollector.com/post',
            'nameOfThisService',
            $this->getClientInterfaceMock(),
            $this->getRequestFactoryInterfaceMock(),
            $this->getStreamFactoryInterfaceMock()
        );

        $status = $exporter->export([new SpanData()]);

        $this->assertSame(SpanExporterInterface::STATUS_SUCCESS, $status);
    }

    public function test_batches_spans_by_resource()
    {
        /** @var StreamFactoryInterface|Mockery\MockInterface */
        $streamFactoryMock = Mockery::mock(StreamFactoryInterface::class);

        /**
         * @psalm-suppress PossiblyInvalidArgument
         */
        $exporter = new HttpCollectorExporter(
            'https://hostOfJaegerCollector.com/post',
            'nameOfThisLogicalApp',
            $this->getClientInterfaceMock(),
            $this->getRequestFactoryInterfaceMock(),
            $streamFactoryMock,
        );

        $spans = [
            (new SpanData())->setResource(ResourceInfo::create(
                new Attributes(),
            )),
            (new SpanData())->setResource(ResourceInfo::create(
                new Attributes([
                    'service.name' => 'nameOfTheOtherLogicalApp'
                ]),
            ))
        ];

        $interceptedContent = [];
        $streamFactoryMock
            ->shouldReceive('createStream')
            ->with('idk1')
            ->once();
        $streamFactoryMock
            ->shouldReceive('createStream')
            ->with('idk2')
            ->once();
            // ->with(Mockery::on(function (string $content) use ($interceptedContent) {
            //     // throw new \Exception("SOS");
            //     // $interceptedContent[] = $content;

            //     return null; //Should be a StreamInterface
            // }))->with(Mockery::on(function (string $content) use ($interceptedContent) {
            //     // throw new \Exception("SOS");
            //     // $interceptedContent[] = $content;

            //     return null; //Should be a StreamInterface
            // }));

        $exporter->export($spans);

        $this->assertEquals(2, count($interceptedContent));
    }
}
