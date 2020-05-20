<?php

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
