<?php

declare(strict_types=1);

use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\API\Trace\Propagation\TraceContextPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagator;
use OpenTelemetry\Context\Propagation\TextMapPropagatorInterface;

class TracerShim implements \OpenTracing\Tracer
{
    private TracerProviderInterface $tracerProvider;
    private ?TextMapPropagatorInterface $textMapPropagator;
    private ?TextMapPropagatorInterface $httpHeadersPropagator;
    private \OpenTracing\ScopeManager $scopeManagerShim;

    public function __construct(
        TracerProviderInterface $tracerProvider,
        TextMapPropagatorInterface $textMapPropagator = null,
        TextMapPropagatorInterface $httpHeadersPropagator = null
    ) {
        $this->tracerProvider = $tracerProvider;
        $this->textMapPropagator = $textMapPropagator;
        $this->httpHeadersPropagator = $httpHeadersPropagator;

        $this->scopeManagerShim = new ScopeManagerShim();
    }

    /**
     * Returns the current {@link ScopeManager}, which may be a noop but may not be null.
     *
     * @return ScopeManager
     */
    public function getScopeManager(): \OpenTracing\ScopeManager
    {
        return $this->scopeManagerShim;
    }

    /**
     * Returns the active {@link Span}. This is a shorthand for
     * Tracer::getScopeManager()->getActive()->getSpan(),
     * and null will be returned if {@link Scope#active()} is null.
     *
     * @return Span|null
     */
    public function getActiveSpan(): ?\OpenTracing\Span
    {
        return $this->scopeManagerShim->getActive();
    }

    /**
     * Starts a new span that is activated on a scope manager.
     *
     * It's also possible to not finish the {@see \OpenTracing\Span} when
     * {@see \OpenTracing\Scope} context expires:
     *
     *     $scope = $tracer->startActiveSpan('...', [
     *         'finish_span_on_close' => false,
     *     ]);
     *     $span = $scope->getSpan();
     *     try {
     *         $span->setTag(Tags\HTTP_METHOD, 'GET');
     *         // ...
     *     } finally {
     *         $scope->close();
     *     }
     *     // $span->finish() is not called as part of Scope deactivation as
     *     // finish_span_on_close is false
     *
     * @param string $operationName
     * @param array|StartSpanOptions $options Same as for startSpan() with
     *     additional option of `finish_span_on_close` that enables finishing
     *     of span whenever a scope is closed. It is true by default.
     *
     * @return Scope A Scope that holds newly created Span and is activated on
     *               a ScopeManager.
     */
    public function startActiveSpan(string $operationName, $options = []): \OpenTracing\Scope
    {
        //Copied from this jaeger implementation - https://github.com/jukylin/jaeger-php/blob/master/src/Jaeger/Jaeger.php#L89
        if (!($options instanceof \OpenTracing\StartSpanOptions)) {
            $options = \OpenTracing\StartSpanOptions::create($options);
        }

        return $this->scopeManagerShim->activate(
            $this->startSpan($operationName, $options),
            $options->shouldFinishSpanOnClose()
        );
    }

    /**
     * Starts and returns a new span representing a unit of work.
     *
     * Whenever `child_of` reference is not passed then
     * {@see \OpenTracing\ScopeManager::getActive()} span is used as `child_of`
     * reference. In order to ignore implicit parent span pass in
     * `ignore_active_span` option set to true.
     *
     * Starting a span with explicit parent:
     *
     *     $tracer->startSpan('...', [
     *         'child_of' => $parentSpan,
     *     ]);
     *
     * @param string $operationName
     * @param array|StartSpanOptions $options See StartSpanOptions for
     *                                        available options.
     *
     * @throws InvalidSpanOptionException for invalid option
     * @throws InvalidReferencesSetException for invalid references set
     * @return \OpenTracing\Span
     *
     * @see \OpenTracing\StartSpanOptions
     */
    public function startSpan(string $operationName, $options = []): \OpenTracing\Span
    {
        //Copied from this jaeger implementation - https://github.com/jukylin/jaeger-php/blob/master/src/Jaeger/Jaeger.php#L89
        if (!($options instanceof \OpenTracing\StartSpanOptions)) {
            $options = \OpenTracing\StartSpanOptions::create($options);
        }

        $tracer = $this->tracerProvider->getTracer("opentracing-shim"); //TODO - see about getting the "current shim library version" and passing it in the 2nd parameter here like the spec suggests - https://github.com/open-telemetry/opentelemetry-specification/blob/main/specification/compatibility/opentracing.md#create-an-opentracing-tracer-shim
        $span = $tracer->spanBuilder($operationName)->startSpan(); //TODO - properly translate the options instead of ignoring them, JS's implementation for reference - https://github.com/open-telemetry/opentelemetry-js/blob/f59c5b268bd60778d7a0d185a6044688f9e3dd51/packages/opentelemetry-shim-opentracing/src/shim.ts#L36

        return new SpanShim($span); //TODO - actually make this constructor accept this
    }

    /**
     * @param SpanContext $spanContext
     * @param string $format
     * @param mixed $carrier
     * @throws UnsupportedFormatException when the format is not recognized by the tracer
     * implementation
     * @return void
     *
     * @see Formats
     */
    public function inject(\OpenTracing\SpanContext $spanContext, string $format, &$carrier): void
    {
        if ($format === \OpenTracing\Formats\BINARY) {
            //TODO - look for an issue to link tracking the lack of support for this (as none of the other langs seem to either)
            throw \OpenTracing\UnsupportedFormatException::forFormat(\OpenTracing\Formats\BINARY);
        }

        if (!($spanContext instanceof SpanContextShim)) {
            return;
        }

        //TODO - determine if $carrier needs any extra validation

        $propagator = $this->getOtelPropagator($format);
        if ($propagator !== null) {
            //TODO - handle the baggage parts of this, as is done here - https://github.com/open-telemetry/opentelemetry-js/blob/f59c5b268bd60778d7a0d185a6044688f9e3dd51/packages/opentelemetry-shim-opentracing/src/shim.ts#L174
            $context = Context::getRoot()->withContextValue(new NonRecordingSpan($spanContext->getSpanContext()));
            //TODO - determine if it's ok to pass null for the PropagationSetterInterface parameter here
            $propagator->inject($carrier, null, $context)
        }
    }

    /**
     * @param string $format
     * @param mixed $carrier
     * @throws UnsupportedFormatException when the format is not recognized by the tracer
     * implementation
     * @return SpanContext|null
     *
     * @see Formats
     */
    public function extract(string $format, $carrier): ?\OpenTracing\SpanContext
    {
        throw new BadMethodCallException('Not implemented');
    }

    /**
     * Allow tracer to send span data to be instrumented.
     *
     * This method might not be needed depending on the tracing implementation
     * but one should make sure this method is called after the request is delivered
     * to the client.
     *
     * As an implementor, a good idea would be to use {@see register_shutdown_function}
     * or {@see fastcgi_finish_request} in order to not to delay the end of the request
     * to the client.
     */
    public function flush(): void
    {
        if ($this->tracerProvider instanceof \OpenTelemetry\SDK\Trace\TracerProviderInterface) {
            $this->tracerProvider->forceFlush();
        }
    }

    private function getOtelPropagator(string $openTracingFormat): ?TextMapPropagator
    {
        switch ($openTracingFormat) {
            case \OpenTracing\Formats\TEXT_MAP;
                return $this->textMapPropagator ?? TraceContextPropagator::getInstance();
            case \OpenTracing\Formats\HTTP_HEADERS;
                return $this->httpHeadersPropagator ?? TraceContextPropagator::getInstance();
            case \OpenTracing\Formats\BINARY;
                return null;
            default:
                return null;
        }
    }
}
