<?php

declare(strict_types=1);

namespace OpenTelemetry\Contrib\Jaeger;

use Psr\Http\Client\ClientInterface;
use Thrift\Transport\THttpClient;
use Thrift\Exception\TTransportException;
use Thrift\Factory\TStringFuncFactory;

class CustomizedTHttpClient extends THttpClient {

    private ClientInterface $psr18Client;

    public function setPsr18HttpClient(ClientInterface $client): self
    {
        $this->psr18Client = $client;

        return $this;
    }

    /**
     * Opens and sends the actual request over the HTTP connection
     *
     * @throws TTransportException if a writing error occurs
     */
    public function flush()
    {
        // God, PHP really has some esoteric ways of doing simple things.
        $host = $this->host_ . ($this->port_ != 80 ? ':' . $this->port_ : '');

        $headers = array();
        $defaultHeaders = array('Host' => $host,
            'Accept' => 'application/x-thrift',
            'User-Agent' => 'PHP/THttpClient',
            'Content-Type' => 'application/x-thrift',
            'Content-Length' => TStringFuncFactory::create()->strlen($this->buf_));
        foreach (array_merge($defaultHeaders, $this->headers_) as $key => $value) {
            $headers[] = "$key: $value";
        }

        $options = $this->context_;

        $baseHttpOptions = isset($options["http"]) ? $options["http"] : array();

        $httpOptions = $baseHttpOptions + array('method' => 'POST',
            'header' => implode("\r\n", $headers),
            'max_redirects' => 1,
            'content' => $this->buf_);
        if ($this->timeout_ > 0) {
            $httpOptions['timeout'] = $this->timeout_;
        }
        $this->buf_ = '';

        $options["http"] = $httpOptions;
        $contextid = stream_context_create($options);
        $this->handle_ = @fopen(
            $this->scheme_ . '://' . $host . $this->uri_,
            'r',
            false,
            $contextid
        );

        // Connect failed?
        if ($this->handle_ === false) {
            $this->handle_ = null;
            $error = 'THttpClient: Could not connect to ' . $host . $this->uri_;
            throw new TTransportException($error, TTransportException::NOT_OPEN);
        }
    }
}