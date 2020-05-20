<?php

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
