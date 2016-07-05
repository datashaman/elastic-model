<?php namespace Datashaman\ElasticModel;

use Elasticsearch\Common\Exceptions\Missing404Exception;
use Log;

class Mappings
{
    protected $type;
    protected $options;
    protected $mapping;

    protected static $typesWithProps = [
        'object',
        'nested',
    ];

    public function __construct($type, $options=[])
    {
        $this->type = $type;
        $this->options = $options;
        $this->mapping = [];
    }

    public function indexes($name, $options=[], callable $callable=null)
    {
        array_set($this->mapping, $name, $options);

        if (is_callable($callable)) {
            $this->mapping = array_add($this->mapping, "$name.type", 'object');

            $type = array_get($this->mapping, "$name.type");
            $properties = in_array($type, static::$typesWithProps) ? 'properties' : 'fields';

            $this->mapping = array_add($this->mapping, "$name.$properties", []);

            call_user_func($callable, $this, "$name.$properties");
        }

        $this->mapping = array_add($this->mapping, "$name.type", 'string');

        return $this;
    }

    public function toArray()
    {
        if (empty($this->options) && empty($this->mapping)) {
            return [];
        }

        $properties = $this->mapping;
        $type = array_merge($this->options, compact('properties'));
        return [ $this->type => $type ];
    }

    public function mergeOptions($options)
    {
        $this->options = array_merge($this->options, $options);
    }
}

class Settings
{
    protected $settings;

    public function __construct($settings=[])
    {
        $this->settings = $settings;
    }

    public function merge($settings)
    {
        $this->settings = array_merge($this->settings, $settings);
        return $this->settings;
    }

    public function toArray()
    {
        return $this->settings;
    }
}


trait Indexing
{
    use Serializing;

    protected $settings;
    protected $mapping;

    public function indexExists($options=[])
    {
        $index = array_get($options, 'index', $this->indexName());
        return $this->client()->indices()->exists(compact('index'));
    }

    public function deleteIndex($options=[])
    {
        $index = array_get($options, 'index', $this->indexName());
        try {
            return $this->client()->indices()->delete(compact('index'));
        } catch (Missing404Exception $e) {
            if (array_get($options, 'force')) {
                Log::error($e->getMessage(), compact('index'));
                return false;
            }

            throw $e;
        }
    }

    public function mappings($options=[], callable $callable=null)
    {
        if (empty($this->mapping)) {
            $this->mapping = new Mappings($this->documentType());
        }

        if (!empty($options)) {
            $this->mapping->mergeOptions($options);
        }

        if (!is_callable($callable)) {
            return $this->mapping;
        }

        call_user_func($callable, $this->mapping);
        return $this->mapping;
    }

    public function mapping($options=[], callable $callable=null)
    {
        return $this->mappings($options, $callable);
    }

    public function settings($settings=[])
    {
        $this->settings = empty($this->settings) ? new Settings($settings) : $this->settings->merge($settings);
        return $this->settings;
    }

    public function createIndex($options=[])
    {
        $index = array_get($options, 'index', $this->indexName());

        if (array_get($options, 'force')) {
            $options['index'] = $index;
            $this->deleteIndex($options);
        }

        if ($this->indexExists(compact('index'))) {
            return false;
        }

        $body = [];

        $settings = $this->settings()->toArray();
        if (!empty($settings)) {
            $body['settings'] = $settings;
        }

        $mappings = $this->mappings()->toArray();
        if (!empty($mappings)) {
            $body['mappings'] = $mappings;
        }

        return $this->client()->indices()->create(compact('index', 'body'));
    }

    public function getDocument($options=[])
    {
        return $this->client()->get($options);
    }

    public function deleteDocument($options=[])
    {
        return $this->client()->delete($options);
    }

    public function indexDocument($options=[])
    {
        return $this->client()->index($options);
    }

    public function updateDocument($options=[])
    {
        return $this->client()->update($options);
    }
}