<?php
namespace Polidog\Todo\Resource\Page\Docs;

use BEAR\Package\Provide\Router\AuraRoute;
use BEAR\RepositoryModule\Annotation\Cacheable;
use BEAR\Resource\Exception\HrefNotFoundException;
use BEAR\Resource\Exception\ResourceNotFoundException;
use BEAR\Resource\ResourceObject;
use BEAR\Sunday\Inject\ResourceInject;

/**
 * @Cacheable(type="view", expiry="never")
 */
class Index extends ResourceObject
{
    use ResourceInject;

    /**
     * @var AuraRoute
     */
    private $route;

    public function __construct(AuraRoute $route)
    {
        $this->route = $route;
    }

    public function onGet(string $rel = null) : ResourceObject
    {
        if ($rel === null) {
            return $this->index();
        }
        $index = $this->resource->options->uri('app://self/')()->body;
        $namedRel = sprintf('%s:%s', $index['_links']['curies']['name'], $rel);
        $links = $index['_links'];
        if (! isset($links[$namedRel]['href'])) {
            throw new ResourceNotFoundException($rel);
        }
        $href = $links[$namedRel]['href'];
        $path = $this->isTemplated($links[$namedRel]) ? $this->match($href) : $href;
        $uri = 'app://self' . $path;
        try {
            $optionsJson = $this->resource->options->uri($uri)()->view;
        } catch (ResourceNotFoundException $e) {
            throw new HrefNotFoundException($href, 0 ,$e);
        }
        $this->body = [
            'doc' => json_decode($optionsJson, true),
            'rel' => $rel,
            'href' => $href
        ];

        return $this;
    }

    private function index()
    {
        $index = $this->resource->uri('app://self/index')()->body;
        $name = $index['_links']['curies']['name'];
        $links = [];
        unset($index['_links']['curies']);
        unset($index['_links']['self']);
        foreach ($index['_links'] as $rel => $value) {
            $newRel = str_replace($name . ':' , '', $rel);
            $links[$newRel] = $value;
        }
        $this->body = [
            'name' => $name,
            'message' => $index['message'],
            'links' => $links
        ];

        return $this;
    }

    private function isTemplated(array $links) : bool
    {
        return (isset($links['templated']) && $links['templated'] === true)  ? true : false;
    }

    private function match($tempaltedPath) : string
    {
        $routes = $this->route->getRoutes();
        foreach ($routes as $route) {
            if ($tempaltedPath == $route->path) {
                return $route->values['path'];
            }
        }

        return $tempaltedPath;
    }
}
