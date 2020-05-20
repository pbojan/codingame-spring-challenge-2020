<?php
// Last compile time: 2020-05-20T18:50:35+02:00


declare(strict_types=1);




















$context = STDIN;

define('DEBUG', false);
define('DEBUG_VERBOSE', false);

fscanf($context, "%d %d", $width, $height);
$mapData = [];
for ($i = 0; $i < $height; $i++) {
    $mapData[] = stream_get_line($context, $width + 1, "\n");
}

$map = new Map($width, $height, $mapData);
$arena = new Arena($map);

while (TRUE) {
    fscanf($context, "%d %d", $myScore, $opponentScore);

    readPacsData($context, $arena);
    readPelletData($context, $arena);

    if (DEBUG) {
        l((string)$map);
    }

    echo implode('|', $arena->execute())  . PHP_EOL;

    $arena->reset();
}

/**
 * @param $context
 * @param Arena $arena
 */
function readPacsData($context, Arena $arena): void
{
    fscanf($context, "%d", $visiblePacCount);
    for ($i = 0; $i < $visiblePacCount; $i++) {
        fscanf($context, "%d %d %d %d %s %d %d", $pacId, $mine, $x, $y, $typeId, $speedTurnsLeft, $abilityCooldown);
        if (strtolower($typeId) === Pac::TYPE_DEAD) {
            continue;
        }
        $arena->updatePac($pacId, (bool)$mine, new Pos($x, $y), $typeId, $speedTurnsLeft, $abilityCooldown);
    }
    $arena->resetPacVision();
}

/**
 * @param $context
 * @param Arena $arena
 */
function readPelletData($context, Arena $arena): void
{
    fscanf($context, "%d", $visiblePelletCount);
    for ($i = 0; $i < $visiblePelletCount; $i++) {
        fscanf($context, "%d %d %d", $x, $y, $value);
        $arena->addPellet(new Pos($x, $y), $value);
    }
}

/**
 * Log values in the coding game console
 * @param mixed $var
 */
function l($var): void
{
    error_log(var_export($var, true));
}


class Arena
{
    public const ENEMY_ID_OFFSET = 100;
    public const TURNS_TO_KEEP_LAST_POSITIONS = 3;

    /**
     * @var int
     */
    private $turn;

    /**
     * @var Map
     */
    private $map;

    /**
     * @var Pac[]
     */
    private $pacs;

    /**
     * Save last known position of pacs.
     * @var Pac[][]
     */
    private $pacsLastPositions = [];

    /**
     * @param Map $map
     */
    public function __construct(Map $map)
    {
        $this->map = $map;
        $this->turn = 0;
    }

    /**
     * Return all commands to be executed this turn.
     *
     * @return AbstractCommand[]
     */
    public function execute(): array
    {
        // calculate the distance between every pac and every other object.
        foreach ($this->pacs as $pac) {
            $pac->setDistanceMap($this->map->calculateDistanceMap($pac));
            if (DEBUG_VERBOSE && $pac->isMine() && $pac->getId() === 0) {
                l('Distance map for pac: ' . $pac->getId() . PHP_EOL);
                $this->map->printMap($pac->getDistanceMap());
            }
        }

        $pacs = $this->getMyPacs();
        $enemyPacs = $this->getEnemyPacs();

        // Priority is important for execution of strategies.
        /** @var AbstractStrategy[] $strategies */
        $strategies = [
            new RunStrategy($this->map, $enemyPacs, $this->pacsLastPositions),
            new ActivateBoostStrategy($this->map),
            new MoveToSuperPelletsStrategy($this->map),
            new ChaseStrategy($this->map, $enemyPacs),
            new CalculateOptimalRouteStrategy($this->map),
            new MoveToClosestPelletStrategy($this->map),
        ];

        $commands = [];
        foreach ($strategies as $strategy) {
            $time = microtime(true);
            array_push($commands, ...$strategy->execute($pacs));
            if (DEBUG_VERBOSE) {
                l(sprintf(
                    'Strategy %s took %f ms!',
                    get_class($strategy),
                    (microtime(true) - $time) * 1000
                ));
            }
        }

        // Check again all pacs if they are in collision
        $pacs = $this->getMyPacs();
        $avoidCollisions = new AvoidCollisionStrategy($this->map, $this->pacsLastPositions, $commands);
        $commands = $avoidCollisions->execute($pacs);

        foreach ($this->pacs as $id => $pac) {
            $this->pacsLastPositions[$this->turn][$id] = clone $pac;
        }

        $this->turn++;

        return $commands;
    }

    /**
     * @param int $pacId
     * @param bool $mine
     * @param Pos $pos
     * @param string $typeId
     * @param int $speedTurnsLeft
     * @param int $abilityCooldown
     */
    public function updatePac(int $pacId, bool $mine, Pos $pos, string $typeId, int $speedTurnsLeft, int $abilityCooldown): void
    {
        $id = $this->getPacId($pacId, $mine);
        $this->pacs[$id] = new Pac($pacId, $mine, $pos, $typeId, $speedTurnsLeft, $abilityCooldown);
        if ($mine) {
            $this->map->addObject($pos, Map::OBJECT_OWN_PAC);
        } else {
            $this->map->addObject($pos, Map::OBJECT_ENEMY_PAC);
        }
    }

    /**
     * Reset pac vision.
     */
    public function resetPacVision(): void
    {
        $pacs = $this->getMyPacs();
        foreach ($pacs as $pac) {
            $this->map->resetVision($pac->getPos());
        }
    }

    /**
     * @param Pos $pos
     * @param int $value
     */
    public function addPellet(Pos $pos, int $value): void
    {
        if ($value === 10) {
            $this->map->addObject($pos, Map::OBJECT_SUPER_PELLET);
        } else {
            $this->map->addObject($pos, Map::OBJECT_PELLET);
        }
    }

    /**
     * Reset all data requred for the next step.
     */
    public function reset(): void
    {
        $this->pacs = [];
        $this->map->resetObjects();

        // Reset pacs last positions for older turns
        if (isset($this->pacsLastPositions[$this->turn - self::TURNS_TO_KEEP_LAST_POSITIONS])) {
            unset($this->pacsLastPositions[$this->turn - self::TURNS_TO_KEEP_LAST_POSITIONS]);
        }
    }

    /**
     * @return Pac[]
     */
    private function getMyPacs(): array
    {
        $myPacs = [];
        foreach ($this->pacs as $pac) {
            if (!$pac->isMine()) {
                continue;
            }

            $myPacs[$pac->getId()] = $pac;
        }

        return $myPacs;
    }

    /**
     * @return Pac[]
     */
    private function getEnemyPacs(): array
    {
        $pacs = [];
        foreach ($this->pacs as $pac) {
            if ($pac->isMine()) {
                continue;
            }

            $pacs[$pac->getId()] = $pac;
        }

        return $pacs;
    }

    /**
     * @param int $pacId
     * @param bool $mine
     *
     * @return int
     */
    private function getPacId(int $pacId, bool $mine): int
    {
        if (!$mine) {
            $pacId += self::ENEMY_ID_OFFSET;
        }

        return $pacId;
    }
}


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


class Pac
{
    public const TYPE_ROCK = 'rock';
    public const TYPE_PAPER = 'paper';
    public const TYPE_SCISSORS = 'scissors';
    public const TYPE_DEAD = 'dead';

    public const WIN_MAP = [
        self::TYPE_PAPER => self::TYPE_ROCK,
        self::TYPE_ROCK => self::TYPE_SCISSORS,
        self::TYPE_SCISSORS => self::TYPE_PAPER,
    ];

    /**
     * @var int
     */
    private $id;

    /**
     * @var bool
     */
    private $isMine;

    /**
     * @var Pos
     */
    private $pos;

    /**
     * @var string
     */
    private $type;

    /**
     * @var int
     */
    private $speedTurnsLeft;

    /**
     * @var int
     */
    private $abilityCooldown;

    /**
     * @var Node[][]
     */
    private $distanceMap;

    /**
     * @param int $id
     * @param bool $isMine
     * @param Pos $pos
     * @param string $type
     * @param int $speedTurnsLeft
     * @param int $abilityCooldown
     */
    public function __construct(int $id, bool $isMine, Pos $pos, string $type, int $speedTurnsLeft, int $abilityCooldown)
    {
        $this->id = $id;
        $this->isMine = $isMine;
        $this->pos = $pos;
        $this->type = strtolower($type);
        $this->speedTurnsLeft = $speedTurnsLeft;
        $this->abilityCooldown = $abilityCooldown;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return Pos
     */
    public function getPos(): Pos
    {
        return $this->pos;
    }

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getSpeedTurnsLeft(): int
    {
        return $this->speedTurnsLeft;
    }

    /**
     * @return int
     */
    public function getAbilityCooldown(): int
    {
        return $this->abilityCooldown;
    }

    /**
     * @return bool
     */
    public function isMine(): bool
    {
        return $this->isMine;
    }

    /**
     * @param Pac $pac
     *
     * @return bool
     */
    public function canEat(Pac $pac): bool
    {
        return static::WIN_MAP[$this->type] === $pac->type;
    }

    /**
     * @param int $range
     *
     * @return Node[]
     */
    public function getNodesInRangeSortedByValue(int $range): array
    {
        $nodes = [];
        foreach ($this->distanceMap as $row) {
            foreach ($row as $node) {
                if ($node->getDistance() > $range) {
                    continue;
                }

                if ($node->getValue() <= Map::MIN_VALUE_FOR_NODE) {
                    continue;
                }

                $nodes[] = $node;
            }
        }

        usort($nodes, static function(Node $a, Node $b) {
            if ($b->getValue() > $a->getValue()) {
                return 1;
            }

            if ($b->getValue() < $a->getValue()) {
                return -1;
            }

            return $a->getDistance() <=> $b->getDistance();
        });

        return $nodes;
    }

    /**
     * @param Pos $pos
     *
     * @return int|null
     */
    public function getDistanceToPos(Pos $pos): ?int
    {
        return isset($this->distanceMap[$pos->getY()][$pos->getX()])
            ? $this->distanceMap[$pos->getY()][$pos->getX()]->getDistance()
            : null;
    }

    /**
     * @param Pos $pos
     *
     * @return float
     */
    public function getValueAtPos(Pos $pos): float
    {
        return isset($this->distanceMap[$pos->getY()][$pos->getX()])
            ? $this->distanceMap[$pos->getY()][$pos->getX()]->getValue()
            : 0.0;
    }

    /**
     * @param Pos $pos
     * @return Node|null
     */
    public function getNodeAtPos(Pos $pos): ?Node
    {
        return $this->distanceMap[$pos->getY()][$pos->getX()] ?? null;
    }

    /**
     * @param Node[][] $distanceMap
     */
    public function setDistanceMap(array $distanceMap): void
    {
        $this->distanceMap = $distanceMap;
    }

    /**
     * @return Node[][]
     */
    public function getDistanceMap(): array
    {
        return $this->distanceMap;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('PAC #%d at %s from type %s', $this->id, $this->pos, $this->type);
    }
}


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


abstract class AbstractCommand
{
    /**
     * @var int
     */
    protected $pacId;

    /**
     * @param int $pacId
     */
    public function __construct(int $pacId)
    {
        $this->pacId = $pacId;
    }

    /**
     * @return int
     */
    public function getPacId(): int
    {
        return $this->pacId;
    }

    /**
     * @return string
     */
    abstract public function __toString(): string;
}


class MoveCommand extends AbstractCommand
{
    /**
     * @var Pos
     */
    private $pos;

    /**
     * @param int $pacId
     * @param Pos $pos
     */
    public function __construct(int $pacId, Pos $pos)
    {
        parent::__construct($pacId);

        $this->pos = $pos;
    }

    /**
     * @return Pos
     */
    public function getPos(): Pos
    {
        return $this->pos;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return "MOVE {$this->pacId} {$this->pos->getX()} {$this->pos->getY()} #{$this->pacId}";
    }
}


class SpeedBoostCommand extends AbstractCommand
{
    /**
     * @return string
     */
    public function __toString(): string
    {
        return "SPEED {$this->pacId} #{$this->pacId}";
    }
}


class SwitchCommand extends AbstractCommand
{
    /**
     * @var string
     */
    private $toType;

    /**
     * @param int $pacId
     * @param string $toType
     */
    public function __construct(int $pacId, string $toType)
    {
        parent::__construct($pacId);

        $this->toType = $toType;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return "SWITCH {$this->pacId} {$this->toType} #{$this->pacId}";
    }
}


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


class ActivateBoostStrategy extends AbstractStrategy
{
    /**
     * If available use the speed boost.
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];
        foreach ($pacs as $id => $pac) {
            if ($pac->getAbilityCooldown() === 0) {
                $commands[] = new SpeedBoostCommand($pac->getId());
                unset($pacs[$id]);
            }
        }

        return $commands;
    }
}


class AvoidCollisionStrategy extends AbstractStrategy
{
    /**
     * @var Pac[][]
     */
    private $pacsLastPositions;

    /**
     * @var AbstractCommand[]
     */
    private $commands;

    /**
     * @param Map $map
     * @param Pac[][] $pacsLastPositions
     * @param AbstractCommand[] $commands
     */
    public function __construct(Map $map, array $pacsLastPositions, array $commands)
    {
        parent::__construct($map);

        $this->pacsLastPositions = $pacsLastPositions;
        $this->commands = $commands;
    }

    /**
     * Check for collisions between own pacs or with enemy pacs and execute strategy
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];

        $skippedTurn = [];
        $lastPositions = array_pop($this->pacsLastPositions);
        foreach ($pacs as $id => $pac) {
            $lastPos = $lastPositions[$id] ?? null;
            if (!$lastPos || !$lastPos->getPos()->isEqual($pac->getPos())) {
                continue;
            }

            if ($pac->getAbilityCooldown() === 9) {
                if (DEBUG) {
                    l(sprintf('%s has used ability so it is on the same pos %d', $pac, $pac->getAbilityCooldown()));
                }
                continue;
            }

            $currentCommand = $this->getCommand($pac->getId());
            if ($currentCommand instanceof MoveCommand) {
                $node = $pac->getNodeAtPos($currentCommand->getPos());
                // Try to find the first pos in the path
                while ($node) {
                    if ($node->getDistance() === 1) {
                        break;
                    }

                    $node = $node->getPrev();
                }

                if (!$node) {
                    l(sprintf('Something went wrong finding the path node for %s!', $pac));
                    continue;
                }

                if (DEBUG) {
                    l(sprintf('Found the next pos for %s in the path is %s', $pac, $node->getPos()));
                }

                $ownPacsAround = $this->getOwnPacsForAdjacentPositions($pacs, $node->getPos(), $skippedTurn);
                if (count($ownPacsAround) > 1) {
                    $skippedTurn[] = $pac->getId();
                    if (DEBUG) {
                        l(sprintf('Found my own PACs are in collision this %s is skipping its turn', $pac));
                    }
                    continue;
                }
            }

            if ($currentCommand instanceof  SpeedBoostCommand) {
                $newType = Pac::WIN_MAP[Pac::WIN_MAP[$pac->getType()]];
                $commands[] = new SwitchCommand($pac->getId(), $newType);

                if (DEBUG) {
                    l(sprintf('%s is switching to %s to type resolve collision with enemy!', $pac, $newType));
                }
                continue;
            }

            $commands[] = $currentCommand;

            if (DEBUG) {
                l(sprintf(
                    '%s is in collision lets see what we can do to fix it %d!',
                    $pac,
                    $pac->getAbilityCooldown()
                ));
            }
        }

        array_push($this->commands, ...$commands);

        return $this->commands;
    }

    /**
     * @param int $pacId
     * @return AbstractCommand
     */
    private function getCommand(int $pacId): AbstractCommand
    {
        foreach ($this->commands as $index => $command) {
            if ($command->getPacId() !== $pacId) {
                continue;
            }

            unset($this->commands[$index]);
            return $command;
        }
    }

    /**
     * @param Pac[] $pacs
     * @param Pos $pos
     * @param int[] $skippedPacs
     *
     * @return Pac[]
     */
    private function getOwnPacsForAdjacentPositions(array $pacs, Pos $pos, array $skippedPacs): array
    {
        $collisionPacs = [];
        foreach (Map::DIRECTIONS as $direction) {
            $newPos = new Pos(
                $pos->getX() + $direction[0],
                $pos->getY() + $direction[1]
            );

            foreach ($pacs as $pac) {
                if (in_array($pac->getId(), $skippedPacs)) {
                    continue;
                }

                if ($pac->getPos()->isEqual($newPos)) {
                    $collisionPacs[] = $pac;
                }
            }
        }

        return $collisionPacs;
    }
}


class CalculateOptimalRouteStrategy extends AbstractStrategy
{
    public const VALUE_RANGE = 5;

    /**
     * Calculate the most optimal route for the rest of the pecs in range and execute the path
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];
        for ($i = static::VALUE_RANGE; $i <= 11; ++$i) {
            foreach ($pacs as $id => $pac) {
                $nodes = $pac->getNodesInRangeSortedByValue($i);
                if (!$nodes) {
                    continue;
                }

                foreach ($nodes as $node) {
                    if ($this->map->isNodeTarget($node)) {
                        continue;
                    }

                    $this->map->addTargetsFromNode($node);
                    $commands[] = new MoveCommand($pac->getId(), $node->getPos());
                    unset($pacs[$id]);

                    if (DEBUG) {
                        l(sprintf(
                            'Moving %s to best value NODE %s with value %f',
                            $pac,
                            $node->getPos(),
                            $node->getValue()
                        ));
                    }
                    break;
                }
            }

            if (empty($pacs)) {
                break;
            }
        }

        return $commands;
    }
}


class ChaseStrategy extends AbstractStrategy
{
    public const DANGER_RANGE = 2;

    /**
     * @var Pac[]
     */
    protected $enemyPacs;

    /**
     * @var int
     */
    protected $chasers = 0;

    /**
     * @param Map $map
     * @param Pac[] $enemyPacs
     */
    public function __construct(Map $map, array $enemyPacs)
    {
        parent::__construct($map);

        $this->enemyPacs = $enemyPacs;
    }

    /**
     * Try to chase close enemies that my pacs can eat them.
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];
        foreach ($this->enemyPacs as $enemyPac) {
            foreach ($pacs as $id => $pac) {
                $distance = $pac->getDistanceToPos($enemyPac->getPos()) ?? 999999;
                if ($distance > static::DANGER_RANGE) {
                    continue;
                }

                if ($this->chasers <= 2 && $pac->canEat($enemyPac) && $enemyPac->getAbilityCooldown() !== 0) {
                    if ($pac->getAbilityCooldown() === 0) {
                        $commands[] = new SpeedBoostCommand($pac->getId());
                        unset($pacs[$id]);
                    } else {
                        $this->chasers++;
                        $commands[] = new MoveCommand($pac->getId(), $enemyPac->getPos());
                        unset($pacs[$id]);

                        if (DEBUG) {
                            l(sprintf('%s is chasing enemy %s!\n', $pac, $enemyPac));
                        }
                    }
                }
            }
        }

        return $commands;
    }
}


class MoveToClosestPelletStrategy extends AbstractStrategy
{
    /**
     * Send the rest of the pacs to hunt for the closes possible pellets
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];
        foreach ($pacs as $id => $pac) {
            $closesObject = $this->map->getClosestObject($pac->getPos(), Map::OBJECT_PELLET);
            if (!$closesObject) {
                $closesObject = $this->map->getClosestObject($pac->getPos(), Map::OBJECT_POSSIBLE_PELLET);
            }

            if (DEBUG) {
                l(sprintf('Closest Object is for %s is %s', $pac, $closesObject));
            }

            if ($closesObject) {
                $this->map->addTarget($closesObject);
                $commands[] = new MoveCommand($pac->getId(), $closesObject);
                unset($pacs[$id]);
            }
        }

        return $commands;
    }
}


class MoveToSuperPelletsStrategy extends AbstractStrategy
{
    public const MAX_DISTANCE = 15;

    /**
     * Match all free pacs to their closes super pellets and move them to eat them.
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];

        // Calculate all distances between super pellets and pacs
        $nodes = [];
        $superPellets = $this->map->getObjectsFromType(Map::OBJECT_SUPER_PELLET);
        foreach ($superPellets as $pellet) {
            foreach ($pacs as $pac) {
                $distance = $pac->getDistanceToPos($pellet) ?? 999999;
                if ($distance > static::MAX_DISTANCE) {
                    continue;
                }

                $node = $pac->getNodeAtPos($pellet);
                $nodes[] = [
                    'node' => $node,
                    'pac' => $pac,
                ];
            }
        }

        usort($nodes, static function(array $a, array $b) {
            /** @var Node $a */
            $a = $a['node'];
            /** @var Node $b */
            $b = $b['node'];

            if ($a->getDistance() > $b->getDistance()) {
                return 1;
            }

            if ($a->getDistance() < $b->getDistance()) {
                return -1;
            }

            return $b->getValue() <=> $a->getValue();
        });

        // send all pacs that are closes to the SUPER_PELLET
        foreach ($nodes as $node) {
            /** @var Pac $pac */
            $pac = $node['pac'];

            /** @var Node $node */
            $node = $node['node'];
            $pellet = $node->getPos();

            if (!isset($pacs[$pac->getId()])) {
                continue;
            }

            if ($this->map->isNodeTarget($node)) {
                continue;
            }

            $this->map->addTargetsFromNode($node);
            $commands[] = new MoveCommand($pac->getId(), $pellet);
            unset($pacs[$pac->getId()]);

            if (DEBUG) {
                l(sprintf(
                    'Moving %s to SUPER PELLET %s because of closest distance %d',
                    $pac,
                    $pellet,
                    $node->getDistance()
                ));
            }
        }

        return $commands;
    }
}


class RunStrategy extends AbstractStrategy
{
    public const DANGER_RANGE = 3;

    /**
     * @var Pac[]
     */
    protected $enemyPacs;

    /**
     * @var Pac[]
     */
    private $pacsLastPositions;

    /**
     * @param Map $map
     * @param Pac[] $enemyPacs
     * @param Pac[][] $pacsLastPositions
     */
    public function __construct(Map $map, array $enemyPacs, array $pacsLastPositions)
    {
        parent::__construct($map);

        $this->enemyPacs = $enemyPacs;
        $this->pacsLastPositions = $pacsLastPositions;
    }

    /**
     * Check if any pac is in danger if so try to run or switch.
     *
     * @param Pac[] $pacs
     *
     * @return AbstractCommand[]
     */
    public function execute(array &$pacs): array
    {
        $commands = [];

        $enemyPacs = $this->enemyPacs;
        foreach ($this->pacsLastPositions as $turn => $turnPacs) {
            foreach ($turnPacs as $pac) {
                if ($pac->isMine()) {
                    continue;
                }

                $enemyPacs[] = $pac;
            }
        }

        foreach ($enemyPacs as $enemyPac) {
            foreach ($pacs as $id => $pac) {
                $distance = $pac->getDistanceToPos($enemyPac->getPos()) ?? 999999;
                if ($distance > static::DANGER_RANGE) {
                    continue;
                }

                if ($enemyPac->canEat($pac)) {
                    // try to switch
                    if ($pac->getAbilityCooldown() === 0) {
                        if ($distance > 2 || ($distance === 2 && $enemyPac->getSpeedTurnsLeft() === 0)) {
                            $commands[] = new SpeedBoostCommand($pac->getId());
                            unset($pacs[$id]);

                            if (DEBUG) {
                                l(sprintf('%s is activating boost to avoid being eaten!', $pac));
                            }
                            continue;
                        }

                        $newType = Pac::WIN_MAP[Pac::WIN_MAP[$enemyPac->getType()]];
                        $commands[] = new SwitchCommand($pac->getId(), $newType);
                        unset($pacs[$id]);

                        if (DEBUG) {
                            l(sprintf('%s is switching to %s to avoid being eaten!', $pac, $newType));
                        }
                    } else {
                        // try to run
                        $positions = $this->map->getMovablePositionsInRange($pac->getPos());
                        $bestPos = null;
                        $bestDistance = 0;
                        $bestValue = 0;
                        foreach ($positions->getPositions() as $pos) {
                            $distance = $enemyPac->getDistanceToPos($pos) ?? 0;
                            if ($distance > $bestDistance) {
                                $bestPos = $pos;
                                $bestDistance = $distance;
                            }

                            $value = $pac->getValueAtPos($pos);
                            if ($distance === $bestDistance && $value > $bestValue) {
                                $bestPos = $pos;
                                $bestValue = $value;
                            }
                        }

                        if ($bestPos) {
                            $commands[] = new MoveCommand($pac->getId(), $bestPos);
                            unset($pacs[$id]);
                        }

                        if (DEBUG) {
                            l(sprintf('%s is trying to run to %s to avoid being eaten!\n', $pac, $bestPos));
                        }
                    }
                }
            }
        }

        return $commands;
    }
}
