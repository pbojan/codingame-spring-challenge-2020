<?php

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
