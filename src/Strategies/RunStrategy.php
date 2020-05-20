<?php

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
