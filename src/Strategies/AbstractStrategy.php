<?php

abstract class AbstractStrategy
{
    protected $map;

    /**
     * @param Map $map
     */
    public function __construct(Map $map)
    {
        $this->map = $map;
    }

    /**
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    abstract public function execute(array &$pacs): array;
}
