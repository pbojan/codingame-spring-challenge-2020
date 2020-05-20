<?php

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
