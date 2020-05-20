<?php

class Map
{
    public const OBJECT_WALL = 0;
    public const OBJECT_PATH = 1;
    public const OBJECT_OWN_PAC = 2;
    public const OBJECT_ENEMY_PAC = 3;
    public const OBJECT_PELLET = 4;
    public const OBJECT_SUPER_PELLET = 5;
    public const OBJECT_POSSIBLE_PELLET = 6;

    public const MIN_VALUE_FOR_NODE = 0.5;
    public const VALUE_MAP = [
        self::OBJECT_SUPER_PELLET => 10.0,
        self::OBJECT_PELLET => 1.0,
        self::OBJECT_POSSIBLE_PELLET => 0.5,
        self::OBJECT_ENEMY_PAC => -2.0,
    ];
    public const RESETABLE_OBJECTS = [
        self::OBJECT_OWN_PAC,
        self::OBJECT_ENEMY_PAC,
        self::OBJECT_SUPER_PELLET,
    ];
    public const MOVABLE_OBJECTS = [
        self::OBJECT_PATH,
        self::OBJECT_PELLET,
        self::OBJECT_SUPER_PELLET,
        self::OBJECT_POSSIBLE_PELLET,
        self::OBJECT_ENEMY_PAC,
    ];
    public const MOVABLE_OBJECTS_ENEMY = [
        self::OBJECT_PATH,
        self::OBJECT_PELLET,
        self::OBJECT_SUPER_PELLET,
        self::OBJECT_POSSIBLE_PELLET,
        self::OBJECT_OWN_PAC,
    ];
    public const OBJECT_MAP = [
        ' ' => self::OBJECT_POSSIBLE_PELLET,
        '#' => self::OBJECT_WALL,
    ];
    public const DIRECTIONS = [
        [1, 0],
        [0, 1],
        [-1, 0],
        [0, -1],
    ];

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $height;

    /**
     * @var int[][]
     */
    private $map = [];

    /**
     * @var Pos[]
     */
    private $targets = [];

    /**
     * All rows (y) that have a tunnel to the other side.
     * @var int[]
     */
    private $tunnels = [];

    /**
     * @param int $width
     * @param int $height
     * @param string[] $mapData
     */
    public function __construct(int $width, int $height, array $mapData)
    {
        $this->width  = $width;
        $this->height = $height;

        $this->initMap($mapData);
    }

    /**
     * @param Pos $pos
     * @param int $object
     */
    public function addObject(Pos $pos, int $object): void
    {
        $this->map[$pos->getY()][$pos->getX()] = $object;
    }

    /**
     * @param Pos $pos
     *
     * @return int
     */
    public function getObject(Pos $pos): int
    {
        return $this->map[$pos->getY()][$pos->getX()];
    }

    /**
     * @param int $type
     *
     * @return Pos[]
     */
    public function getObjectsFromType(int $type): array
    {
        $objects = [];
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                if ($this->map[$y][$x] !== $type) {
                    continue;
                }

                $objects[] = new Pos($x, $y);
            }
        }

        return $objects;
    }

    /**
     * @param Pos $pos
     */
    public function addTarget(Pos $pos): void
    {
        $this->targets[] = $pos;
    }

    /**
     * @param Node $node
     */
    public function addTargetsFromNode(Node $node): void
    {
        do {
            $this->addTarget($node->getPos());
            $node = $node->getPrev();
        } while ($node);
    }

    /**
     * @param Node $node
     *
     * @return bool
     */
    public function isNodeTarget(Node $node): bool
    {
        do {
            if ($this->isTarget($node->getPos())) {
                return true;
            }
            $node = $node->getPrev();
        } while ($node);

        return false;
    }

    /**
     * @param Pos $pos
     * @param int $object
     *
     * @return Pos|null
     */
    public function getClosestObject(Pos $pos, int $object): ?Pos
    {
        $visitedNodes = [];
        $queue = $this->getMovablePositions($pos);
        while (!empty($queue)) {
            $next = array_shift($queue);
            if ($this->getObject($next) === $object && !$this->isTarget($next)) {
                return $next;
            }

            $visitedNodes[$next->getX()][$next->getY()] = true;
            foreach ($this->getMovablePositions($next) as $next) {
                if (isset($visitedNodes[$next->getX()][$next->getY()])) {
                    continue;
                }

                $queue[] = $next;
            }
        }

        return null;
    }

    /**
     * Calculate the distance map to everything using BFS.
     *
     * @param Pac $pac
     *
     * @return Node[][]
     */
    public function calculateDistanceMap(Pac $pac): array
    {
        /** @var Node[][] $distanceMap */
        $distanceMap = [];
        $startNode = new Node($pac->getPos(), 0, 0);
        $queue = [$startNode];
        while (!empty($queue)) {
            $currentNode = array_shift($queue);

            $pos = $currentNode->getPos();
            $currentDistance = isset($distanceMap[$pos->getY()][$pos->getX()])
                ? $distanceMap[$pos->getY()][$pos->getX()]->getDistance()
                : 999999999;
            if ($currentNode->getDistance() < $currentDistance) {
                $distanceMap[$pos->getY()][$pos->getX()] = $currentNode;
            }

            foreach ($this->getMovablePositions($currentNode->getPos(), $pac->isMine()) as $next) {
                if (isset($distanceMap[$next->getY()][$next->getX()])) {
                    continue;
                }

                $value = $currentNode->getValue() + $this->getObjectValueForPosition($next);
                $node = new Node($next, $currentNode->getDistance() + 1, $value);
                $node->setPrev($currentNode);
                $queue[] = $node;
            }
        }

        return $distanceMap;
    }

    /**
     * @param Pos $pos
     * @return PositionCollection
     */
    public function getMovablePositionsInRange(Pos $pos): PositionCollection
    {
        $positions = new PositionCollection($this->getMovablePositions($pos));
        $firstLevel = $positions->getPositions();
        foreach ($firstLevel as $position) {
            $positions->addPositions($this->getMovablePositions($position));
        }

        return $positions;
    }

    /**
     * @param Pos $pos
     * @param bool $isMine
     *
     * @return Pos[]
     */
    public function getMovablePositions(Pos $pos, bool $isMine = true): array
    {
        $movableObjest = static::MOVABLE_OBJECTS;
        if (!$isMine) {
            $movableObjest = static::MOVABLE_OBJECTS_ENEMY;
        }

        $positions = [];
        $directions = static::DIRECTIONS;
        shuffle($directions);
        foreach ($directions as $direction) {
            $newPos = new Pos(
                $pos->getX() + $direction[0],
                $pos->getY() + $direction[1]
            );

            $newPos = $this->useTunnel($newPos);
            if (!$this->isValidPosition($newPos)) {
                continue;
            }

            if (in_array($this->getObject($newPos), $movableObjest, true)) {
                $positions[] = $newPos;
            }
        }

        return $positions;
    }

    /**
     * @param Node[][] $distanceMap
     */
    public function printMap(array $distanceMap): void
    {
        $valueMap = '';
        $map = '';
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $distance = -1;
                $value = 0;
                if (isset($distanceMap[$y][$x])) {
                    $distance = $distanceMap[$y][$x]->getDistance();
                    $value = $distanceMap[$y][$x]->getValue();
                }
                $map .= str_pad((string)($distance), 2, "0", STR_PAD_LEFT) . ' ';
                $valueMap .= str_pad((string)(round($value)), 2, "0", STR_PAD_LEFT) . ' ';
            }
            $map .= PHP_EOL;
            $valueMap .= PHP_EOL;
        }

        l($map);
        l($valueMap);
    }

    /**
     * Every turn reset all objects that are dynamic.
     */
    public function resetObjects(): void
    {
        $this->targets = [];

        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                if (in_array($this->map[$y][$x], static::RESETABLE_OBJECTS, true)) {
                    $this->map[$y][$x] = static::OBJECT_PATH;
                }

                if ($this->map[$y][$x] === static::OBJECT_PELLET) {
                    $this->map[$y][$x] = static::OBJECT_POSSIBLE_PELLET;
                }
            }
        }
    }

    /**
     * @param Pos $pos
     */
    public function resetVision(Pos $pos): void
    {
        // reset vision left from pac
        for ($i = $pos->getX() - 1; $i >= 0; $i--) {
            $object = $this->map[$pos->getY()][$i];
            if ($object === static::OBJECT_POSSIBLE_PELLET || $object === static::OBJECT_PELLET) {
                $this->map[$pos->getY()][$i] = static::OBJECT_PATH;
            }

            if ($object === static::OBJECT_WALL) {
                break;
            }
        }

        // reset vision right from pac
        for ($i = $pos->getX() + 1; $i < $this->width; $i++) {
            $object = $this->map[$pos->getY()][$i];
            if ($object === static::OBJECT_POSSIBLE_PELLET || $object === static::OBJECT_PELLET) {
                $this->map[$pos->getY()][$i] = static::OBJECT_PATH;
            }

            if ($object === static::OBJECT_WALL) {
                break;
            }
        }

        // reset vision up from pac
        for ($i = $pos->getY() - 1; $i >= 0; $i--) {
            $object = $this->map[$i][$pos->getX()];
            if ($object === static::OBJECT_POSSIBLE_PELLET || $object === static::OBJECT_PELLET) {
                $this->map[$i][$pos->getX()] = static::OBJECT_PATH;
            }

            if ($object === static::OBJECT_WALL) {
                break;
            }
        }

        // reset vision down from pac
        for ($i = $pos->getY() + 1; $i < $this->height; $i++) {
            $object = $this->map[$i][$pos->getX()];
            if ($object === static::OBJECT_POSSIBLE_PELLET || $object === static::OBJECT_PELLET) {
                $this->map[$i][$pos->getX()] = static::OBJECT_PATH;
            }

            if ($object === static::OBJECT_WALL) {
                break;
            }
        }
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        $value = sprintf('Arena: W=%d | H=%d%s', $this->width, $this->height, PHP_EOL);
        for ($y = 0; $y < $this->height; ++$y) {
            for ($x = 0; $x < $this->width; ++$x) {
                $value .= $this->map[$y][$x] . ' ';
            }
            $value .= PHP_EOL;
        }

        return $value;
    }

    /**
     * @param Pos $pos
     *
     * @return bool
     */
    private function isTarget(Pos $pos): bool
    {
        foreach ($this->targets as $target) {
            if ($pos->isEqual($target)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $mapData
     */
    private function initMap(array $mapData): void
    {
        foreach ($mapData as $i => $row) {
            $cols = str_split($row);
            foreach ($cols as $j => $col) {
                $this->map[$i][$j] = static::OBJECT_MAP[$col];
            }
        }

        foreach ($this->map as $y => $row) {
            if ($row[0] === static::OBJECT_WALL) {
                continue;
            }

            $this->tunnels[] = $y;
        }
    }

    /**
     * Check if pos should use tunnel or not and return new pos.
     *
     * @param Pos $pos
     * @return Pos
     */
    private function useTunnel(Pos $pos): Pos
    {
        if (!in_array($pos->getY(), $this->tunnels)) {
            return $pos;
        }

        if ($pos->getX() === -1) {
            return new Pos($this->width - 1, $pos->getY());
        }

        if ($pos->getX() === $this->width) {
            return new Pos(0, $pos->getY());
        }

        return $pos;
    }

    private function isValidPosition(Pos $pos): bool
    {
        if ($pos->getX() < 0 || $pos->getX() >= $this->width) {
            return false;
        }

        if ($pos->getY() < 0 || $pos->getY() >= $this->height) {
            return false;
        }

        return true;
    }

    /**
     * @param Pos $pos
     *
     * @return float
     */
    private function getObjectValueForPosition(Pos $pos): float
    {
        $object = $this->getObject($pos);

        return static::VALUE_MAP[$object] ?? 0.0;
    }
}
