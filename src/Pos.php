<?php

class Pos
{
    /**
     * @var int
     */
    private $x;
    /**
     * @var int
     */
    private $y;

    /**
     * @param int $x
     * @param int $y
     */
    public function __construct(int $x, int $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    /**
     * @return int
     */
    public function getX(): int
    {
        return $this->x;
    }

    /**
     * @return int
     */
    public function getY(): int
    {
        return $this->y;
    }

    /**
     * @param Pos $pos
     *
     * @return bool
     */
    public function isEqual(Pos $pos): bool
    {
        return $this->x === $pos->getX() && $this->y === $pos->getY();
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('POS (%d, %d)', $this->x, $this->y);
    }
}
