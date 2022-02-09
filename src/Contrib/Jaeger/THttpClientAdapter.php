<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use Psr\Http\Client\ClientInterface;
use Thrift\Transport\THttpClient;
use Thrift\Transport\TTransport;

class THttpClientAdapter extends TTransport
{
    private ClientInterface $psr18Client;

    private THttpClient $tHttpClientInstance;

    public function __construct($host, $port = 80, $uri = '', $scheme = 'http', array $context = array())
    {
        $this->tHttpClientInstance = new THttpClient(
            $host,
            $port,
            $uri,
            $scheme,
            $context
        );
    }

    public function setPsr18CompliantHttpClient(ClientInterface $client): self 
    {
        $this->psr18Client = $client;

        return $this;
    }

    /**
     * Whether this transport is open.
     *
     * @return boolean true if open
     */
    public function isOpen()
    {
        return $this->tHttpClientInstance->isOpen();
    }

    /**
     * Open the transport for reading/writing
     *
     * @throws TTransportException if cannot open
     */
    public function open()
    {
        $this->tHttpClientInstance->open();
    }

    /**
     * Close the transport.
     */
    public function close()
    {
        $this->tHttpClientInstance->close();
    }

    /**
     * Read some data into the array.
     *
     * @param int $len How much to read
     * @return string The data that has been read
     * @throws TTransportException if cannot read any more data
     */
    public function read($len)
    {
        return $this->tHttpClientInstance->read($len);
    }

    /**
     * Writes some data into the pending buffer
     *
     * @param string $buf The data to write
     * @throws TTransportException if writing fails
     */
    public function write($buf)
    {
        $this->tHttpClientInstance->write($buf);
    }

    /**
     * Opens and sends the actual request over the HTTP connection
     *
     * @throws TTransportException if a writing error occurs
     */
    public function flush()
    {
        $this->tHttpClientInstance->flush();
    }
}
