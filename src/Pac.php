<?php

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
