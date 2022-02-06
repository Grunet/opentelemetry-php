<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Process;
use Jaeger\Thrift\Span as JTSpan;
use Jaeger\Thrift\Tag;
use Jaeger\Tracer;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Thrift\Protocol\TBinaryProtocol;
use Thrift\Protocol\TProtocol;
use Thrift\Transport\THttpClient;

class JaegerSender
{
    private string $serviceName;

    private TProtocol $protocol;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var Tracer
     */
    private $tracer;

    public function __construct(
        string $serviceName,
        string $host,
        string $port,
        LoggerInterface $logger = null
    ) {
        $this->serviceName = $serviceName;
        
        $transport = new THttpClient(
            $host,
            $port,
        );
        $this->protocol = new TBinaryProtocol($transport);

        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * @param JTSpan[] $spans
     */
    public function send(array $spans): void
    {
        try {
            if (empty($spans)) {
                return;
            }
    
            if (empty($this->tracer)) {
                return ;
            }

            /** @var Tag[] $tags */
            $tags = [];
    
            //TODO - determine what of this is still needed and how to adapt it
            // foreach ($this->tracer->getTags() as $k => $v) {
            //     if (!in_array($k, $this->mapper->getSpecialSpanTags())) {
            //         if (strpos($k, $this->mapper->getProcessTagsPrefix()) !== 0) {
            //             continue ;
            //         }
    
            //         $quoted = preg_quote($this->mapper->getProcessTagsPrefix());
            //         $k = preg_replace(sprintf('/^%s/', $quoted), '', $k);
            //     }
    
            //     if ($k === JAEGER_HOSTNAME_TAG_KEY) {
            //         $k = "hostname";
            //     }
    
            //     $tags[] = new Tag([
            //         "key" => $k,
            //         "vType" => TagType::STRING,
            //         "vStr" => $v
            //     ]);
            // }
    
            // $tags[] = new Tag([
            //     "key" => "format",
            //     "vType" => TagType::STRING,
            //     "vStr" => "jaeger.thrift"
            // ]);
    
            // $tags[] = new Tag([
            //     "key" => "ip",
            //     "vType" => TagType::STRING,
            //     "vStr" => $this->tracer->getIpAddress()
            // ]);
    
            $batch = new Batch([
                "spans" => $spans,
                "process" => new Process([
                    "serviceName" => $this->serviceName,
                    "tags" => $tags
                ])
            ]);
    
            $batch->write($this->protocol);
            $this->protocol->getTransport()->flush();
        } catch (\Exception $e) {
            $this->logger->warning($e->getMessage());
        }
    }
}
