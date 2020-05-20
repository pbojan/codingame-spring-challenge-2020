<?php

declare(strict_types=1);

require_once 'src/Arena.php';
require_once 'src/Map.php';
require_once 'src/Pac.php';
require_once 'src/Pos.php';
require_once 'src/Node.php';
require_once 'src/PositionCollection.php';
require_once 'src/Commands/AbstractCommand.php';
require_once 'src/Commands/MoveCommand.php';
require_once 'src/Commands/SpeedBoostCommand.php';
require_once 'src/Commands/SwitchCommand.php';
require_once 'src/Strategies/AbstractStrategy.php';
require_once 'src/Strategies/ActivateBoostStrategy.php';
require_once 'src/Strategies/AvoidCollisionStrategy.php';
require_once 'src/Strategies/CalculateOptimalRouteStrategy.php';
require_once 'src/Strategies/ChaseStrategy.php';
require_once 'src/Strategies/MoveToClosestPelletStrategy.php';
require_once 'src/Strategies/MoveToSuperPelletsStrategy.php';
require_once 'src/Strategies/RunStrategy.php';

$context = fopen('inputs/input.txt', 'rb');

define('DEBUG', false);
define('DEBUG_VERBOSE', false);

fscanf($context, "%d %d", $width, $height);
$mapData = [];
for ($i = 0; $i < $height; $i++) {
    $mapData[] = stream_get_line($context, $width + 1, "\n");
}

$map = new Map($width, $height, $mapData);
$arena = new Arena($map);

while (TRUE) {
    fscanf($context, "%d %d", $myScore, $opponentScore);

    readPacsData($context, $arena);
    readPelletData($context, $arena);

    if (DEBUG) {
        l((string)$map);
    }

    echo implode('|', $arena->execute())  . PHP_EOL;

    $arena->reset();
}

/**
 * @param $context
 * @param Arena $arena
 */
function readPacsData($context, Arena $arena): void
{
    fscanf($context, "%d", $visiblePacCount);
    for ($i = 0; $i < $visiblePacCount; $i++) {
        fscanf($context, "%d %d %d %d %s %d %d", $pacId, $mine, $x, $y, $typeId, $speedTurnsLeft, $abilityCooldown);
        if (strtolower($typeId) === Pac::TYPE_DEAD) {
            continue;
        }
        $arena->updatePac($pacId, (bool)$mine, new Pos($x, $y), $typeId, $speedTurnsLeft, $abilityCooldown);
    }
    $arena->resetPacVision();
}

/**
 * @param $context
 * @param Arena $arena
 */
function readPelletData($context, Arena $arena): void
{
    fscanf($context, "%d", $visiblePelletCount);
    for ($i = 0; $i < $visiblePelletCount; $i++) {
        fscanf($context, "%d %d %d", $x, $y, $value);
        $arena->addPellet(new Pos($x, $y), $value);
    }
}

/**
 * Log values in the coding game console
 * @param mixed $var
 */
function l($var): void
{
    error_log(var_export($var, true));
}
