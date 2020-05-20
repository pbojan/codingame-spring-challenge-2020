<?php

class SwitchCommand extends AbstractCommand
{
    /**
     * @var string
     */
    private $toType;

    /**
     * @param int $pacId
     * @param string $toType
     */
    public function __construct(int $pacId, string $toType)
    {
        parent::__construct($pacId);

        $this->toType = $toType;
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return "SWITCH {$this->pacId} {$this->toType} #{$this->pacId}";
    }
}
