<?php

declare(strict_types=1);

namespace OpenTelemetry\Tests\Contrib\Unit;

use PHPUnit\Framework\TestCase;
use OpenTelemetry\Contrib\Jaeger\SpanConverter;
use OpenTelemetry\Tests\SDK\Util\SpanData;

/**
 * @coversDefaultClass SpanConverter
 */
class JaegerSpanConverterTest extends TestCase
{
    /**
     * @test
     */
    public function shouldConvertASpanToAPayloadForJaeger()
    {
        $span = (new SpanData());

        $converter = new SpanConverter('test.name');
        $row = $converter->convert([$span])[0];

        $this->assertCount(0, $row);
    }
}
