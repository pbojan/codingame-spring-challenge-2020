<?php

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
