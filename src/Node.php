<?php

class Node
{
    /**
     * @var Pos
     */
    private $pos;

    /**
     * @var int
     */
    private $distance;

    /**
     * @var float
     */
    private $value;

    /**
     * @var Node|null
     */
    private $prev;

    /**
     * @param Pos $pos
     * @param int $distance
     * @param float $value
     */
    public function __construct(Pos $pos, int $distance, float $value)
    {
        $this->pos = $pos;
        $this->distance = $distance;
        $this->value = $value;
    }

    /**
     * @return Pos
     */
    public function getPos(): Pos
    {
        return $this->pos;
    }

    /**
     * @return int
     */
    public function getDistance(): int
    {
        return $this->distance;
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }

    /**
     * @return Node|null
     */
    public function getPrev(): ?Node
    {
        return $this->prev;
    }

    /**
     * @param Node $prev
     */
    public function setPrev(Node $prev): void
    {
        $this->prev = $prev;
    }
}
