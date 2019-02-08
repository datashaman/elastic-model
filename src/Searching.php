<?php

namespace Datashaman\Elasticsearch\Model;

class SearchRequest
{
    public $class;
    public $definition;
    public $options;

    public function __construct($class, $query, $options = [])
    {
        $this->class = $class;
        $this->options = $options;

        $index = array_get($options, 'index', $class::indexName());
        $type = array_get($options, 'type', $class::documentType());

        if (method_exists($query, 'toArray')) {
            $body = $query->toArray();
        } elseif (is_array($query)) {
            $body = $query;
        } elseif (is_string($query) && preg_match('/^\s*{/', $query)) {
            $body = $query;
        } else {
            $body = [
                'query' => [
                    'query_string' => compact('query'),
                ],
            ];
        }

        $this->definition = array_merge(compact('index', 'type', 'body'), $options);
    }

    public function execute()
    {
        $class = $this->class;
        $result = $class::elasticsearch()->client()->search($this->definition);

        return $result;
    }

    public function update($attributes)
    {
        foreach ($attributes as $key => $value) {
            $this->definition[$key] = $value;
        }
    }
}

class MultiSearchRequest extends SearchRequest
{
    public function __construct($class, $body, $options = [])
    {
        $this->class = $class;
        $this->options = $options;

        $this->definition = array_merge(compact('body'), $options);
    }

    public function execute()
    {
        $class = $this->class;
        $result = $class::elasticsearch()->client()->msearch($this->definition);

        return $result;
    }
}

trait Searching
{
    public function search($query, $options = [])
    {
        $search = new SearchRequest($this->class, $query, $options);
        $response = new Response($search, $options);

        return $response;
    }

    public function msearch($queries, $options = [])
    {
        $search = new MultiSearchRequest($this->class, $queries, $options);
        $response = new MultiResponse($search, $options);

        return $response;
    }
}
