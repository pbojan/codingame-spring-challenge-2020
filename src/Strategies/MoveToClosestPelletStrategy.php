<?php

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
