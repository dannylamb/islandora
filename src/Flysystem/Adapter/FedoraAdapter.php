<?php

namespace Drupal\islandora\Flysystem\Adapter;

use Islandora\Chullo\IFedoraApi;
use League\Flysystem\AdapterInterface;
use League\Flysystem\Adapter\Polyfill\StreamedCopyTrait;
use League\Flysystem\Config;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\StreamWrapper;
use Symfony\Component\HttpFoundation\File\MimeType\MimeTypeGuesserInterface;

class FedoraAdapter implements AdapterInterface {

    use StreamedCopyTrait;

    protected $fedora;
    protected $mimeTypeGuesser;

    public function __construct(IFedoraApi $fedora, MimeTypeGuesserInterface $mime_type_guesser) {
        $this->fedora = $fedora;
        $this->mimeTypeGuesser = $mime_type_guesser;
    }

    public function has($path) {
        $response = $this->fedora->getResourceHeaders($path);
        return $response->getStatusCode() == 200;
    }

    public function read($path) {
        $meta = $this->readStream($path);

        if (!$meta) {
            return false;
        }

        if (isset($meta['stream'])) {
            $meta['contents'] = stream_get_contents($meta['stream']);
            fclose($meta['stream']);
            unset($meta['stream']);
        }

        return $meta;
    }

    public function readStream($path) {
        $response = $this->fedora->getResource($path);

        if ($response->getStatusCode() != 200) {
            return false;
        }

        $meta = $this->getMetadataFromHeaders($response);
        $meta['path'] = $path;

        if ($meta['type'] == 'file') {
          $meta['stream'] = StreamWrapper::getResource($response->getBody());
        }

        return $meta;
    }

    public function getMetadata($path) {
        $response = $this->fedora->getResourceHeaders($path);

        if ($response->getStatusCode() != 200) {
            return false;
        }

        $meta = $this->getMetadataFromHeaders($response);
        $meta['path'] = $path;
        return $meta;
    }

    public function getSize($path) {
        return $this->getMetadata($path);
    }

    public function getMimetype($path) {
        return $this->getMetadata($path);
    }

    public function getTimestamp($path) {
        return $this->getMetadata($path);
    }

    public function getVisibility($path) {
        return $this->getMetadata($path);
    }

    protected function getMetadataFromHeaders($response) {
        $last_modified = \DateTime::createFromFormat(
            \DateTime::RFC1123,
            $response->getHeader('Last-Modified')[0]
        );

        // NonRDFSource's are considered files.  Everything else is a
        // directory.
        $type = 'dir';
        $links = Psr7\parse_header($response->getHeader('Link'));
        foreach ($links as $link) {
            if ($link['rel'] == 'type' && $link[0] == '<http://www.w3.org/ns/ldp#NonRDFSource>') {
                $type = 'file';
                break;
            }
        }

        $meta = [
          'type' => $type,
          'timestamp' => $last_modified->getTimestamp(),
          'visibility' => AdapterInterface::VISIBILITY_PUBLIC,
        ];

        if ($type == 'file') {
          $meta['size'] = $response->getHeader('Content-Length')[0];
          $meta['mimetype'] = $response->getHeader('Content-Type')[0];
        }

        return $meta;
    }

    public function listContents($directory = '', $recursive = false) {
        // Strip leading and trailing whitespace and /'s.
        $normalized = trim($directory);
        $normalized = trim($normalized, '/');

        // Exit early if it's a file.
        $meta = $this->getMetadata($normalized);
        if ($meta['type'] == 'file') {
            return [];
        }
        // Get the resource from Fedora.
        $response = $this->fedora->getResource($normalized, ['Accept' => 'application/ld+json']);
        $jsonld = (string) $response->getBody();
        $graph = json_decode($jsonld, true);

        $uri = $this->fedora->getBaseUri() . $normalized;

        // Hack it out of the graph.
        // There may be more than one resource returned.
        $resource = [];
        foreach ($graph as $elem) {
            if (isset($elem['@id']) && $elem['@id'] == $uri) {
                $resource = $elem;
                break;
            }
        }

        // Exit early if resource doesn't contain other resources.
        if (!isset($resource['http://www.w3.org/ns/ldp#contains'])) {
            return [];
        }

        // Collapse uris to a single array.
        $contained = array_map(
            function ($elem) {
                return $elem['@id'];
            },
            $resource['http://www.w3.org/ns/ldp#contains']
        );

        // Exit early if not recursive.
        if (!$recursive) {
            // Transform results to their flysystem metadata.
            return array_map(
                [$this, 'transformToMetadata'],
                $contained
            );
        }

        // Recursively get containment for ancestors
        $ancestors = [];

        foreach ($contained as $child_uri) {
          $child_directory = explode($this->fedora->getBaseUri(), $child_uri)[1];
          $ancestors = array_merge($this->listContents($child_directory, $recursive), $ancestors);
        }

        // // Transform results to their flysystem metadata.
        return array_map(
            [$this, 'transformToMetadata'],
            array_merge($ancestors, $contained)
        );
    }

    protected function transformToMetadata($uri) {
        if (is_array($uri)) {
            return $uri;
        }
        $exploded = explode($this->fedora->getBaseUri(), $uri);
        return $this->getMetadata($exploded[1]);
    }

    public function write($path, $contents, Config $config) {
        $headers = [
            'Content-Type' => $this->mimeTypeGuesser->guess($path),
        ];

        $response = $this->fedora->saveResource(
            $path,
            $contents,
            $headers
        );

        $code = $response->getStatusCode();
        if (!in_array($code, [201, 204])) {
            return false;
        }

        return $this->getMetadata($path);
    }

    public function writeStream($path, $contents, Config $config) {
        return $this->write($path, $contents, $config);
    }

    public function update($path, $contents, Config $config) {
        return $this->write($path, $contents, $config);
    }

    public function updateStream($path, $contents, Config $config) {
        return $this->write($path, $contents, $config);
    }

    public function delete($path) {
        $response = $this->fedora->deleteResource($path);

        $code = $response->getStatusCode();
        return in_array($code, [204, 404]);
    }

    public function deleteDir($dirname) {
        return $this->delete($dirname);
    }

    public function rename($path, $newpath) {
        $this->copy($path, $newpath);
        return $this->delete($path);
    }

    public function createDir($dirname, Config $config) {
        $response = $this->fedora->saveResource(
            $dirname
        );

        $code = $response->getStatusCode();
        if (!in_array($code, [201, 204])) {
            return false;
        }

        return $this->getMetadata($path);
    }

    public function setVisibility($path, $visibility) {
        return $this->getMetadata($path);
    }
}

