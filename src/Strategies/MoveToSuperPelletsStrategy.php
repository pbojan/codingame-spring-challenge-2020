<?php

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
