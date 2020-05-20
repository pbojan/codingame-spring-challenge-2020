<?php

class PositionCollection
{
    /**
     * @var Pos[]
     */
    private $positions;

    /**
     * Map to check if the position already exists in the collection
     * @var bool[][]
     */
    private $map = [];

    /**
     * @param array $positions
     */
    public function __construct(array $positions = [])
    {
        $this->positions = $positions;
    }

    /**
     * @param Pos $position
     */
    public function addPosition(Pos $position): void
    {
        if (!empty($this->map[$position->getY()][$position->getX()])) {
            return;
        }

        $this->positions[] = $position;
        $this->map[$position->getY()][$position->getX()] = true;
    }

    /**
     * @param Pos[] $positions
     */
    public function addPositions(array $positions): void
    {
        foreach ($positions as $position) {
            $this->addPosition($position);
        }
    }

    /**
     * @return Pos[]
     */
    public function getPositions(): array
    {
        return $this->positions;
    }
}
