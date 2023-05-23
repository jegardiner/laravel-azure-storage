<?php

declare(strict_types=1);

namespace Matthewbdaly\LaravelAzureStorage;

use Illuminate\Support\Arr;
use League\Flysystem\AzureBlobStorage\AzureBlobStorageAdapter as BaseAzureBlobStorageAdapter;
use Matthewbdaly\LaravelAzureStorage\Exceptions\InvalidCustomUrl;
use Matthewbdaly\LaravelAzureStorage\Exceptions\KeyNotSet;
use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Blob\BlobSharedAccessSignatureHelper;

/**
 * Blob storage adapter
 *
 * @internal
 */
final class AzureBlobStorageAdapter extends BaseAzureBlobStorageAdapter
{

    /**
     * Create a new AzureBlobStorageAdapter instance.
     *
     * @param \MicrosoftAzure\Storage\Blob\BlobRestProxy $client Client.
     * @param string $container Container.
     * @param string $key
     * @param string|null $url URL.
     * @param string $prefix Prefix.
     * @param string $visibilityHandling Handling of visibility support.
     *
     * @throws InvalidCustomUrl URL is not valid.
     */
    public function __construct(
        private BlobRestProxy $client,
        private string $container,
        private string $key = null,
        private string $url = null,
        private string $prefix = '',
        private string $visibilityHandling = self::ON_VISIBILITY_IGNORE
    ) {
        parent::__construct($client, $container, $prefix);
        if ($url && !filter_var($url, FILTER_VALIDATE_URL)) {
            throw new InvalidCustomUrl();
        }
    }

    /**
     * Get the file URL by given path.
     *
     * @param string $path Path.
     *
     * @return string
     */
    public function getUrl(string $path)
    {
        if ($this->url) {
            return rtrim($this->url, '/') . '/' . ($this->container === '$root' ? '' : $this->container . '/') . ($this->prefix ? $this->prefix . '/' : '') . ltrim($path, '/');
        }
        return $this->client->getBlobUrl($this->container, $path);
    }

    /**
     * Generate Temporary Url with SAS query
     *
     * @param string $path
     * @param \DateTime|string $ttl
     * @param array $options
     *
     * @return string
     */
    public function getTemporaryUrl(string $path, $ttl, array $options = [])
    {
        $path = $this->prefix ? $this->prefix . '/' . $path : $path;
        $resourceName = (empty($path) ? $this->container : $this->container  . '/' . $path);
        if (!$this->key) {
            throw new KeyNotSet();
        }
        $sas = new BlobSharedAccessSignatureHelper($this->client->getAccountName(), $this->key);
        $sasString = $sas->generateBlobServiceSharedAccessSignatureToken(
            (string)Arr::get($options, 'signed_resource', 'b'),
            $resourceName,
            (string)Arr::get($options, 'signed_permissions', 'r'),
            $ttl,
            (string)Arr::get($options, 'signed_start', ''),
            (string)Arr::get($options, 'signed_ip', ''),
            (string)Arr::get($options, 'signed_protocol', 'https'),
            (string)Arr::get($options, 'signed_identifier', ''),
            (string)Arr::get($options, 'cache_control', ''),
            (string)Arr::get($options, 'content_disposition', ''),
            (string)Arr::get($options, 'content_encoding', ''),
            (string)Arr::get($options, 'content_language', ''),
            (string)Arr::get($options, 'content_type', '')
        );

        return sprintf('%s?%s', $this->getUrl($path), $sasString);
    }
}
