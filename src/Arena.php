<?php

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
