<?php

class MoveCommand extends AbstractCommand
{
    /**
     * @var Pos
     */
    private $pos;

    /**
     * @param int $pacId
     * @param Pos $pos
     */
    public function __construct(int $pacId, Pos $pos)
    {
        parent::__construct($pacId);

        $this->pos = $pos;
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
    public function __toString(): string
    {
        return "MOVE {$this->pacId} {$this->pos->getX()} {$this->pos->getY()} #{$this->pacId}";
    }
}
