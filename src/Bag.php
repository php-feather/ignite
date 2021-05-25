<?php

namespace Feather\Ignite;

/**
 * Description of Container
 *
 * @author fcarbah
 */
class Bag implements \ArrayAccess, \IteratorAggregate
{

    protected $items = [];

    public function __construct(array $items = [])
    {
        $this->items = $items;
    }

    /**
     *
     * @param string|int $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->items[$name] ?? null;
    }

    /**
     * Add data to container
     * @param string|int $key
     * @param mixed $value
     */
    public function addItem($key, $value)
    {
        $this->items[$key] = $value;
    }

    /**
     * Add multiple items to container
     * @param array $items
     */
    public function addItems(array $items)
    {
        $this->items = array_merge($this->items, $items);
    }

    /**
     * Get all items in the container
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     *
     * @param string|int $key
     * @return bool
     */
    public function hasKey($key): bool
    {
        return array_key_exists($key, $this->items);
    }

    /**
     *
     * {@inheritdoc}
     */
    public function offsetExists($offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     *
     * {@inheritdoc}
     */
    public function offsetGet($offset)
    {
        return $this->items[$offset] ?? null;
    }

    /**
     *
     * {@inheritdoc}
     */
    public function offsetSet($offset, $value): void
    {
        $this->items[$offset] = $value;
    }

    /**
     *
     * {@inheritdoc}
     */
    public function offsetUnset($offset): void
    {
        if (array_key_exists($offset, $this->items)) {
            unset($this->items[$offset]);
        }
    }

    /**
     *
     * {@inheritdoc}
     */
    public function getIterator(): \Traversable
    {
        return $this->items;
    }

}
