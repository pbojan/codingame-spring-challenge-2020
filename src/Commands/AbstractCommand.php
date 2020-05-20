<?php

abstract class AbstractCommand
{
    /**
     * @var int
     */
    protected $pacId;

    /**
     * @param int $pacId
     */
    public function __construct(int $pacId)
    {
        $this->pacId = $pacId;
    }

    /**
     * @return int
     */
    public function getPacId(): int
    {
        return $this->pacId;
    }

    /**
     * @return string
     */
    abstract public function __toString(): string;
}
